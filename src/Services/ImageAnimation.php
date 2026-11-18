<?php namespace Seiger\sGallery\Services;

/**
 * Inspect local GIF and WebP containers before selecting a single-frame driver.
 *
 * Read metadata from the stream so a cache lookup does not decode the pixels.
 *
 * @internal
 * @since 1.9.0
 */
final class ImageAnimation
{
    /** Detect GIF or WebP animation using the container rather than the filename. @since 1.9.0 */
    public static function isAnimated(string $path, int $type): bool
    {
        if ($type !== IMAGETYPE_GIF && $type !== IMAGETYPE_WEBP) {
            return false;
        }
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            if ($type === IMAGETYPE_GIF) {
                return self::hasMultipleGifFrames($stream);
            }
            $header = fread($stream, 21);
            return $header !== false && strlen($header) === 21 && str_starts_with($header, 'RIFF') &&
                substr($header, 8, 8) === 'WEBPVP8X' && (ord($header[20]) & 0x02) !== 0;
        } finally {
            fclose($stream);
        }
    }

    /**
     * Read playback count (zero means infinite) and per-frame delays in milliseconds.
     *
     * Read the container before decoding. Imagick can round WebP delays on read.
     * @return array{loops: int, delays: list<int>}|null
     * @since 1.9.0
     */
    public static function timing(string $path, int $type): ?array
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return null;
        }
        try {
            return match ($type) {
                IMAGETYPE_GIF => self::gifTiming($stream),
                IMAGETYPE_WEBP => self::webpTiming($stream),
                default => null,
            };
        } finally {
            fclose($stream);
        }
    }

    /** Read GIF Graphic Control and Netscape loop extensions. @since 1.9.0 */
    private static function gifTiming($stream): ?array
    {
        $header = fread($stream, 13);
        if ($header === false || strlen($header) !== 13 ||
            !in_array(substr($header, 0, 6), ['GIF87a', 'GIF89a'], true) || !self::skipGifColorTable($stream, ord($header[10]))) {
            return null;
        }

        $loops = 1;
        $delay = 0;
        $delays = [];
        while (($marker = fread($stream, 1)) !== '' && $marker !== false) {
            if ($marker === "\x3b") {
                return count($delays) > 1 ? ['loops' => $loops, 'delays' => $delays] : null;
            }
            if ($marker === "\x2c") {
                $descriptor = fread($stream, 9);
                if ($descriptor === false || strlen($descriptor) !== 9 ||
                    !self::skipGifColorTable($stream, ord($descriptor[8])) || (string) fread($stream, 1) === '') {
                    return null;
                }
                $delays[] = $delay;
                $delay = 0;
            } elseif ($marker === "\x21") {
                $label = fread($stream, 1);
                if ($label === "\xf9") {
                    $control = fread($stream, 6);
                    if ($control === false || strlen($control) !== 6 || $control[0] !== "\x04" ||
                        $control[5] !== "\0" || (ord($control[1]) & 2) !== 0) {
                        return null;
                    }
                    $delay = unpack('v', substr($control, 2, 2))[1] * 10;
                    continue;
                }
                if ($label === "\xff") {
                    $size = fread($stream, 1);
                    if ($size !== "\x0b") {
                        return null;
                    }
                    $application = fread($stream, 11);
                    if (in_array($application, ['NETSCAPE2.0', 'ANIMEXTS1.0'], true)) {
                        $loop = fread($stream, 4);
                        if ($loop === false || strlen($loop) !== 4 || !str_starts_with($loop, "\x03\x01")) {
                            return null;
                        }
                        $repeats = unpack('v', substr($loop, 2))[1];
                        $loops = $repeats === 0 ? 0 : $repeats + 1;
                    }
                } elseif ($label !== "\xfe") {
                    return null;
                }
            } else {
                return null;
            }

            if (!self::skipGifSubBlocks($stream)) {
                return null;
            }
        }

        return null;
    }

    /** Read exact delays and loop count from ANMF and ANIM chunks. @since 1.9.0 */
    private static function webpTiming($stream): ?array
    {
        $header = fread($stream, 12);
        if ($header === false || strlen($header) !== 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8) !== 'WEBP') {
            return null;
        }
        $end = unpack('V', substr($header, 4, 4))[1] + 8;
        if ($end > (fstat($stream)['size'] ?? 0)) {
            return null;
        }

        $loops = null;
        $delays = [];
        while (($offset = ftell($stream)) !== false && $offset + 8 <= $end) {
            $chunk = fread($stream, 8);
            if ($chunk === false || strlen($chunk) !== 8) {
                return null;
            }
            $type = substr($chunk, 0, 4);
            $length = unpack('V', substr($chunk, 4))[1];
            $next = $offset + 8 + $length + ($length % 2);
            if ($next > $end) {
                return null;
            }
            if ($type === 'ANIM' && $length === 6) {
                $data = fread($stream, 6);
                if ($data === false || strlen($data) !== 6) {
                    return null;
                }
                $loops = unpack('v', substr($data, 4))[1];
            } elseif ($type === 'ANMF' && $length >= 16) {
                $data = fread($stream, 16);
                if ($data === false || strlen($data) !== 16) {
                    return null;
                }
                $delays[] = unpack('V', substr($data, 12, 3) . "\0")[1];
            }
            if (fseek($stream, $next) !== 0) {
                return null;
            }
        }

        return $loops !== null && count($delays) > 1 && ftell($stream) === $end ? ['loops' => $loops, 'delays' => $delays] : null;
    }

    /** Count GIF image descriptors, skipping color tables and data sub-blocks. @since 1.9.0 */
    private static function hasMultipleGifFrames($stream): bool
    {
        $header = fread($stream, 13);
        if ($header === false || strlen($header) !== 13 ||
            !in_array(substr($header, 0, 6), ['GIF87a', 'GIF89a'], true) || !self::skipGifColorTable($stream, ord($header[10]))) {
            return false;
        }

        $frames = 0;
        while (($marker = fread($stream, 1)) !== '') {
            if ($marker === "\x2c") {
                $descriptor = fread($stream, 9);
                if ($descriptor === false || strlen($descriptor) !== 9 ||
                    !self::skipGifColorTable($stream, ord($descriptor[8])) || (string) fread($stream, 1) === '') {
                    return false;
                }
                if (++$frames === 2) {
                    return true;
                }
            } elseif ($marker !== "\x21" || (string) fread($stream, 1) === '') {
                return false;
            }

            if (!self::skipGifSubBlocks($stream)) {
                return false;
            }
        }

        return false;
    }

    /** Advance past a global or local RGB color table if the GIF flags include it. @since 1.9.0 */
    private static function skipGifColorTable($stream, int $flags): bool
    {
        return ($flags & 0x80) === 0 || fseek($stream, 3 * (2 << ($flags & 7)), SEEK_CUR) === 0;
    }

    /** Skip the data sub-blocks of a GIF image or extension. @since 1.9.0 */
    private static function skipGifSubBlocks($stream): bool
    {
        while (($size = fread($stream, 1)) !== "\0") {
            if ($size === false || $size === '' || fseek($stream, ord($size), SEEK_CUR) !== 0) {
                return false;
            }
        }
        return true;
    }
}
