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
