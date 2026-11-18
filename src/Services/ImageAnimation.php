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

            do {
                $size = fread($stream, 1);
                if ($size === false || $size === '' || fseek($stream, ord($size), SEEK_CUR) !== 0) {
                    return false;
                }
            } while ($size !== "\x00");
        }

        return false;
    }

    /** Advance past a global or local RGB color table if the GIF flags include it. @since 1.9.0 */
    private static function skipGifColorTable($stream, int $flags): bool
    {
        return ($flags & 0x80) === 0 || fseek($stream, 3 * (2 << ($flags & 7)), SEEK_CUR) === 0;
    }
}
