<?php namespace Seiger\sGallery\Services;

use GdImage;
use Imagick;
use ImagickPixel;
use RuntimeException;

/** Pixel and drawing primitives shared by the phpThumb compatibility filters. @internal */
final class PhpThumbRaster
{
    public static function number(string $value, float $min, float $max, float $default = 0): float
    {
        return is_numeric($value) && is_finite((float) $value) ? max($min, min($max, (float) $value)) : $default;
    }

    public static function color(string $hex, string $fallback = '000000'): string
    {
        return preg_match('/\A[a-f0-9]{6}\z/i', $hex) ? $hex : $fallback;
    }

    /** @return array{int,int,int} */
    public static function rgb(string $hex): array
    {
        $rgb = (int) hexdec($hex);
        return [$rgb >> 16, ($rgb >> 8) & 255, $rgb & 255];
    }

    public static function gray(int $r, int $g, int $b): int
    {
        return (int) round(0.30 * $r + 0.59 * $g + 0.11 * $b);
    }

    public static function budget(float $pixels): void
    {
        if (!ImageMemoryBudget::allows($pixels, 0, PHP_SAPI === 'cli')) {
            throw new RuntimeException('Insufficient memory budget for the thumbnail filter.');
        }
    }

    /** Use one scanline at a time and preserve the original pixel opacity unless changed by the filter. */
    public static function map(NativeImage $image, callable $pixel, callable $check): NativeImage
    {
        return self::scan($image, $pixel, $check, true);
    }

    public static function read(NativeImage $image, callable $pixel, callable $check): void
    {
        self::scan($image, $pixel, $check, false);
    }

    private static function scan(NativeImage $image, callable $pixel, callable $check, bool $write): NativeImage
    {
        $image = $image->withPixelAlpha();
        $frame = $image->frame();
        [$width, $height] = $image->dimensions();
        $alpha = $frame instanceof Imagick && $frame->getImageAlphaChannel();
        self::budget((float) $width * $height + $width * 16);
        if ($write) {
            if ($frame instanceof Imagick) {
                if (!$alpha && !$frame->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE)) {
                    throw new RuntimeException('Cannot initialize thumbnail opacity.');
                }
            } else {
                imagealphablending($frame, false);
            }
        }
        for ($y = 0; $y < $height; $y++) {
            $check();
            if ($frame instanceof Imagick) {
                $row = $frame->exportImagePixels(0, $y, $width, 1, 'RGBA', Imagick::PIXEL_CHAR);
                if ($row === false) {
                    throw new RuntimeException('Cannot read thumbnail pixels.');
                }
                for ($x = 0; $x < $width; $x++) {
                    $i = $x * 4;
                    $rgba = $pixel($row[$i], $row[$i + 1], $row[$i + 2], $alpha ? $row[$i + 3] : 255, $x, $y);
                    if ($write) {
                        [$row[$i], $row[$i + 1], $row[$i + 2], $row[$i + 3]] = $rgba;
                    }
                }
                if ($write && !$frame->importImagePixels(0, $y, $width, 1, 'RGBA', Imagick::PIXEL_CHAR, $row)) {
                    throw new RuntimeException('Cannot update thumbnail pixels.');
                }
                continue;
            }
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($frame, $x, $y);
                $rgba = $pixel(($color >> 16) & 255, ($color >> 8) & 255, $color & 255, 255 - (($color >> 24) * 2), $x, $y);
                if ($write) {
                    [$r, $g, $b, $opacity] = $rgba;
                    imagesetpixel($frame, $x, $y, (((255 - $opacity) >> 1) << 24) | ($r << 16) | ($g << 8) | $b);
                }
            }
        }
        return $image;
    }

    public static function canvas(NativeImage $image, int $width, int $height): NativeImage
    {
        self::budget((float) $width * $height);
        if ($image->frame() instanceof Imagick) {
            $frame = new Imagick();
            if (!$frame->newImage($width, $height, 'transparent', 'PNG')) {
                throw new RuntimeException('Cannot create a thumbnail filter canvas.');
            }
        } else {
            $frame = imagecreatetruecolor($width, $height);
            if ($frame === false) {
                throw new RuntimeException('Cannot create a thumbnail filter canvas.');
            }
            imagealphablending($frame, false);
            imagesavealpha($frame, true);
            imagefill($frame, 0, 0, 0x7f000000);
        }
        return $image->withFrame($frame);
    }

    public static function paint(string $hex, float $opacity): ImagickPixel
    {
        [$r, $g, $b] = self::rgb($hex);
        return new ImagickPixel("rgba($r,$g,$b,$opacity)");
    }

    public static function gdColor(string $hex, float $opacity): int
    {
        return ((int) round(127 * (1 - $opacity)) << 24) | (int) hexdec($hex);
    }
}

