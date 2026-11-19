<?php namespace Seiger\sGallery\Services;

/**
 * Inspect the color metadata needed to choose a safe native raster path.
 *
 * @internal
 * @since 1.9.0
 */
final class ImageColor
{
    public const SRGB = 'srgb';
    public const ICC = 'icc';
    public const UNSUPPORTED = 'unsupported';

    /**
     * Inspect metadata GD cannot expose on static GIF, WebP, AVIF and BMP inputs.
     * Alpha is conservative; profiles require Imagick, and unknown color/orientation stays original.
     * @return array{bool, string}
     * @since 1.9.0
     */
    public static function fromRaster(string $path, int $type): array
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) return [false, self::UNSUPPORTED];
        try {
            $size = (int) (fstat($stream)['size'] ?? 0);
            if ($type === IMAGETYPE_GIF) {
                // ICCRGBG1012 is the GIF ICC application identifier. Search with bounded buffers.
                $tail = '';
                while (!feof($stream)) {
                    $bytes = fread($stream, 65536);
                    if ($bytes === false || $bytes === '') break;
                    $bytes = $tail . $bytes;
                    if (str_contains($bytes, 'ICCRGBG1012')) return [true, self::ICC];
                    $tail = substr($bytes, -9);
                }
                return [true, self::SRGB];
            }
            if ($type === IMAGETYPE_WEBP) {
                $header = fread($stream, 12);
                if (strlen($header) !== 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8) !== 'WEBP') {
                    return [false, self::UNSUPPORTED];
                }
                $alpha = false;
                $color = self::SRGB;
                $end = unpack('V', substr($header, 4, 4))[1] + 8;
                if ($end > $size) return [false, self::UNSUPPORTED];
                while (ftell($stream) + 8 <= $end) {
                    $chunk = fread($stream, 8);
                    $length = unpack('V', substr($chunk, 4))[1];
                    $next = ftell($stream) + $length + ($length % 2);
                    if ($next > $end) return [false, self::UNSUPPORTED];
                    $kind = substr($chunk, 0, 4);
                    if ($kind === 'ICCP') $color = self::ICC;
                    // GD does not expose WebP EXIF orientation; do not silently discard it.
                    if ($kind === 'EXIF') return [false, self::UNSUPPORTED];
                    if ($kind === 'VP8X' && $length >= 1) {
                        $flags = ord(fread($stream, 1));
                        if ($flags & 0x20) $color = self::ICC;
                        if ($flags & 0x08) return [false, self::UNSUPPORTED];
                        $alpha = (bool) ($flags & 0x10);
                    }
                    $alpha = $alpha || $kind === 'ALPH' || $kind === 'VP8L';
                    if (fseek($stream, $next) !== 0) return [false, self::UNSUPPORTED];
                }
                return [$alpha, $color];
            }
            if ($type === IMAGETYPE_AVIF) {
                // The alpha auxiliary image is not exposed by PHP's header reader.
                return [true, self::avifColor($stream, $size)];
            }
            if ($type === IMAGETYPE_BMP) {
                $header = fread($stream, 138);
                if (strlen($header) < 26 || substr($header, 0, 2) !== 'BM') return [false, self::UNSUPPORTED];
                $dib = unpack('V', substr($header, 14, 4))[1];
                if (in_array($dib, [12, 40, 52, 56], true)) return [true, self::SRGB];
                if (in_array($dib, [108, 124], true) && strlen($header) >= 74) {
                    $space = unpack('V', substr($header, 70, 4))[1];
                    return [true, in_array($space, [0x73524742, 0x57696e20], true) ? self::SRGB
                        : ($space === 0x4d424544 ? self::ICC : self::UNSUPPORTED)];
                }
            }
            return [false, self::UNSUPPORTED];
        } finally {
            fclose($stream);
        }
    }

    /** Inspect AVIF color properties with bounded ISO box traversal, excluding compressed image data. @since 1.9.0 */
    private static function avifColor($stream, int $end, int $depth = 0): string
    {
        if ($depth > 4) return self::UNSUPPORTED;
        $color = self::SRGB;
        while (($offset = ftell($stream)) + 8 <= $end) {
            $header = fread($stream, 8);
            if (strlen($header) !== 8) return self::UNSUPPORTED;
            $length = unpack('N', $header)[1];
            $type = substr($header, 4);
            if ($length === 0) $length = $end - $offset;
            // Extended boxes are unusual for metadata; preserve them until a reader can inspect them.
            if ($length < 8 || $length > $end - $offset) return self::UNSUPPORTED;
            $next = $offset + $length;
            if ($type === 'ftyp') {
                if ($length < 16 || $length > 1024) return self::UNSUPPORTED;
                $brands = fread($stream, $length - 8);
                for ($i = 0; $i + 4 <= strlen($brands); $i += 4) {
                    if ($i !== 4 && substr($brands, $i, 4) === 'avis') return self::UNSUPPORTED;
                }
            } elseif (in_array($type, ['meta', 'iprp', 'ipco'], true)) {
                if ($type === 'meta' && ($length < 12 || fseek($stream, 4, SEEK_CUR) !== 0)) return self::UNSUPPORTED;
                $nested = self::avifColor($stream, $next, $depth + 1);
                if ($nested === self::UNSUPPORTED) return $nested;
                if ($nested === self::ICC) $color = self::ICC;
            } elseif ($type === 'colr') {
                if ($length < 12) return self::UNSUPPORTED;
                $kind = fread($stream, 4);
                if (in_array($kind, ['prof', 'rICC'], true)) {
                    $color = self::ICC;
                } elseif ($kind === 'nclx' && $length >= 19) {
                    $nclx = unpack('nprimaries/ntransfer/nmatrix/Crange', fread($stream, 7));
                    if ($nclx['primaries'] !== 1 || !in_array($nclx['transfer'], [1, 13], true) ||
                        !in_array($nclx['matrix'], [0, 1, 5, 6], true)) return self::UNSUPPORTED;
                } else {
                    return self::UNSUPPORTED;
                }
            } elseif (in_array($type, ['irot', 'imir'], true)) {
                return self::UNSUPPORTED;
            }
            if (fseek($stream, $next) !== 0) return self::UNSUPPORTED;
        }
        return $color;
    }

    /** Classify the ICC segments and reject unprofiled four-component JPEGs. @since 1.9.0 */
    public static function fromJpeg(string $path, int $channels): string
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return self::UNSUPPORTED;
        }

        try {
            if (fread($stream, 2) !== "\xff\xd8") {
                return self::UNSUPPORTED;
            }
            while (fread($stream, 1) === "\xff") {
                do {
                    $marker = fread($stream, 1);
                } while ($marker === "\xff");
                if ($marker === "\xda" || $marker === "\xd9") {
                    return in_array($channels, [1, 3], true) ? self::SRGB : self::UNSUPPORTED;
                }
                if ($marker === "\x01" || ($marker >= "\xd0" && $marker <= "\xd7")) {
                    continue;
                }
                $size = fread($stream, 2);
                if ($size === false || strlen($size) !== 2) {
                    break;
                }
                $length = unpack('n', $size)[1] - 2;
                if ($marker === "\xe2" && $length >= 12) {
                    if (fread($stream, 12) === "ICC_PROFILE\0") {
                        return self::ICC;
                    }
                    $length -= 12;
                }
                if ($length < 0 || fseek($stream, $length, SEEK_CUR) !== 0) {
                    break;
                }
            }
        } finally {
            fclose($stream);
        }

        return self::UNSUPPORTED;
    }

    /** Inspect PNG alpha, color and animation chunks before the pixel stream. @return array{bool, string} @since 1.9.0 */
    public static function fromPng(string $path): array
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return [false, self::UNSUPPORTED];
        }

        $alpha = false;
        $icc = $srgb = $unsupported = false;
        $standard = true;
        try {
            $header = (string) fread($stream, 33);
            if (strlen($header) !== 33 || !str_starts_with($header, "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR")) {
                return [false, self::UNSUPPORTED];
            }
            $alpha = in_array(ord($header[25]), [4, 6], true);
            while (strlen($header = (string) fread($stream, 8)) === 8) {
                $type = substr($header, 4);
                $length = unpack('N', $header)[1];
                if ($type === 'IDAT') {
                    return [$alpha, match (true) {
                        $unsupported => self::UNSUPPORTED,
                        $icc => self::ICC,
                        $srgb || $standard => self::SRGB,
                        default => self::UNSUPPORTED,
                    }];
                }
                if ($length > 0x7fffffff || $type === 'IEND') {
                    break;
                }
                $alpha = $alpha || $type === 'tRNS';
                $icc = $icc || $type === 'iCCP';
                $srgb = $srgb || $type === 'sRGB';
                $unsupported = $unsupported || $type === 'cICP' || $type === 'acTL';
                $expected = match ($type) {
                    'gAMA' => pack('N', 45455),
                    'cHRM' => pack('N*', 31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000),
                    default => null,
                };
                if ($expected !== null && $length === strlen($expected)) {
                    $actual = fread($stream, $length);
                    $standard = $standard && $actual === $expected;
                    $length = 0;
                } elseif ($expected !== null) {
                    $standard = false;
                }
                // Skip chunk data that was not read above and skip the CRC.
                if (fseek($stream, $length + 4, SEEK_CUR) !== 0) {
                    break;
                }
            }
        } finally {
            fclose($stream);
        }

        return [$alpha, self::UNSUPPORTED];
    }

    /** Load the bundled sRGB target profile once per process. @since 1.9.0 */
    public static function srgbProfile(): string
    {
        static $profile = null;
        $profile ??= @file_get_contents(__DIR__ . '/../Resources/sRGB-v2-micro.icc');
        if ($profile === false) {
            throw new \RuntimeException('The gallery sRGB profile is unavailable.');
        }

        return $profile;
    }
}
