<?php namespace Seiger\sGallery\Services;

use GdImage;
use Imagick;

/** Validate and apply watermark parameters with the current image driver. @since 1.7.0 */
class Watermark
{
    /** Resolve a local image inside the site's public filesystem. @since 1.7.0 */
    public static function path(string $input): string
    {
        if (str_starts_with($input, EVO_SITE_URL)) $input = substr($input, strlen(EVO_SITE_URL));
        $root = realpath(EVO_BASE_PATH);
        $path = realpath(EVO_BASE_PATH . ltrim($input, '/'));
        if (!$path || !$root || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) ||
            !is_file($path) || !@getimagesize($path)) {
            throw new \InvalidArgumentException('Watermark must be an existing local image supported by the image driver.');
        }
        return $path;
    }

    /** Validate options before cache lookup or processing. @since 1.7.0 */
    public static function options(array $options): array
    {
        $defaults = ['position' => 'bottom-right', 'width' => '20%', 'opacity' => 100, 'padding' => '3%'];
        $options = array_replace($defaults, $options);
        if (array_diff(array_keys($options), array_keys($defaults))) throw new \InvalidArgumentException('Unknown watermark option.');
        $positions = ['top', 'top-center', 'top-middle', 'center-top', 'middle-top',
            'top-right', 'right-top', 'left', 'left-center', 'left-middle', 'center-left',
            'middle-left', 'right', 'right-center', 'right-middle', 'center-right',
            'middle-right', 'bottom-left', 'left-bottom', 'bottom', 'bottomCenter',
            'bottom-middle', 'center-bottom', 'middle-bottom', 'bottom-right',
            'right-bottom', 'center', 'middle', 'centerCenter', 'middleMiddle',
            'top-left', 'left-top'];
        if (!in_array($options['position'], $positions, true)) throw new \ValueError('Invalid watermark position.');
        foreach (['width', 'padding'] as $key) {
            if (!preg_match('/^\d+(?:%|px)?$/D', (string)$options[$key])) throw new \InvalidArgumentException('Watermark dimensions require integer pixels or percentages.');
            $n = (int)$options[$key];
            if (($key === 'width' && $n < 1) || (str_ends_with((string)$options[$key], '%') && $n > 100)) throw new \InvalidArgumentException('Invalid watermark dimension.');
        }
        if (!is_numeric($options['opacity']) || $options['opacity'] < 0 || $options['opacity'] > 100) throw new \InvalidArgumentException('Watermark opacity must be between 0 and 100.');
        $options['opacity'] = (int)$options['opacity'];
        return $options;
    }

    /**
     * Composite the mark over a transformed frame using that frame's driver.
     * The caller may reuse the prepared overlay across equally sized animation frames.
     *
     * @param array{file: string, hash: string, options: array} $mark Validated Builder parameters.
     * @param array{GdImage|Imagick, int, int}|null $overlay The resized mark and its position.
     * @since 1.9.0
     */
    public static function applyNative(NativeImage $image, array $mark, string $format, ?array &$overlay = null): NativeImage
    {
        if ($mark['options']['opacity'] === 0) {
            return $image;
        }

        $overlay ??= self::prepareNative($image, $mark, $format);
        [$stamp, $x, $y] = $overlay;
        $image = $image->withPixelAlpha();
        $frame = $image->frame();
        if ($frame instanceof Imagick && $stamp instanceof Imagick) {
            $success = $frame->compositeImage($stamp, Imagick::COMPOSITE_OVER, $x, $y);
        } elseif ($frame instanceof GdImage && $stamp instanceof GdImage) {
            $success = imagealphablending($frame, true) && imagecopy($frame, $stamp, $x, $y, 0, 0, imagesx($stamp), imagesy($stamp));
        } else {
            $success = false;
        }

        if (!$success) {
            throw new \RuntimeException('Cannot composite the native gallery watermark.');
        }
        return $image;
    }

    /** Read and resize a still mark with the same driver as the frame it will cover. @since 1.9.0 */
    private static function prepareNative(NativeImage $image, array $mark, string $format): array
    {
        $path = self::verifiedPath($mark);
        if (ImageAnimation::isAnimated($path, @getimagesize($path)[2] ?? 0)) {
            throw new \RuntimeException('The native gallery watermark must be a still image.');
        }
        $base = rtrim(realpath(EVO_BASE_PATH), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base)));
        $driver = $image->frame() instanceof Imagick ? 'imagick' : 'gd';
        $stamp = NativeImage::open($relative, $format, $driver)->withPixelAlpha();
        if (hash_file('sha256', $path) !== $mark['hash']) {
            throw new \RuntimeException('Watermark changed during processing.');
        }

        $options = $mark['options'];
        [$canvasWidth, $canvasHeight] = $image->dimensions();
        [$width, $paddingX, $paddingY] = self::dimensions($options, $canvasWidth, $canvasHeight);
        [$markWidth, $markHeight] = $stamp->dimensions();
        $height = max(1, round($width * ($markHeight / $markWidth)));
        if (!ImageMemoryBudget::allows((float) $width * $height + (float) $markWidth * $markHeight, (int) @filesize($path), PHP_SAPI === 'cli')) {
            throw new \RuntimeException('Insufficient memory budget to resize the gallery watermark.');
        }
        $stamp = $stamp->fit('stretch', $width, (int) $height);
        [$width, $height] = $stamp->dimensions();

        $position = $options['position'];
        $center = in_array($position, ['center', 'middle', 'centerCenter', 'middleMiddle'], true);
        // Larger offsets already put the entire mark outside the canvas.
        $paddingX = min($paddingX, $canvasWidth + $width);
        $paddingY = min($paddingY, $canvasHeight + $height);
        $x = match (true) {
            str_contains($position, 'left') => $paddingX,
            str_contains($position, 'right') => $canvasWidth - $width - $paddingX,
            default => intdiv($canvasWidth, 2) - intdiv($width, 2) + ($center ? $paddingX : 0),
        };
        $y = match (true) {
            str_contains($position, 'top') => $paddingY,
            str_contains($position, 'bottom') => $canvasHeight - $height - $paddingY,
            default => intdiv($canvasHeight, 2) - intdiv($height, 2) + ($center ? $paddingY : 0),
        };

        $frame = $stamp->frame();
        self::setNativeOpacity($frame, $options['opacity']);
        return [$frame, $x, $y];
    }

    /** Multiply per-pixel opacity so transparent pixels stay transparent. @since 1.9.0 */
    private static function setNativeOpacity(GdImage|Imagick $frame, int $opacity): void
    {
        if ($opacity === 100) {
            return;
        }
        $factor = $opacity / 100;
        if ($frame instanceof Imagick) {
            if (!$frame->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE) ||
                !$frame->evaluateImage(Imagick::EVALUATE_MULTIPLY, $factor, Imagick::CHANNEL_ALPHA)) {
                throw new \RuntimeException('Cannot set the native gallery watermark opacity.');
            }
            return;
        }

        imagealphablending($frame, false);
        $width = imagesx($frame);
        $height = imagesy($frame);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($frame, $x, $y);
                $alpha = 127 - (int) round((127 - ($pixel >> 24)) * $factor);
                imagesetpixel($frame, $x, $y, ($alpha << 24) | ($pixel & 0xffffff));
            }
        }
    }

    /** Resolve width and independent padding values against the transformed frame. @since 1.9.0 */
    private static function dimensions(array $options, int $canvasWidth, int $canvasHeight): array
    {
        $padding = (int) $options['padding'];
        $percent = str_ends_with((string) $options['padding'], '%');
        $paddingX = $percent ? (int) ($canvasWidth * $padding / 100) : $padding;
        $paddingY = $percent ? (int) ($canvasHeight * $padding / 100) : $padding;
        $width = (int) $options['width'];
        if (str_ends_with((string) $options['width'], '%')) {
            $width = max(1, (int) ($canvasWidth * $width / 100));
        }
        return [$width, $paddingX, $paddingY];
    }

    /** Resolve the local file and ensure it still matches the cache parameters. @since 1.9.0 */
    private static function verifiedPath(array $mark): string
    {
        $path = self::path($mark['file']);
        if (hash_file('sha256', $path) !== $mark['hash']) {
            throw new \RuntimeException('Watermark changed during processing.');
        }
        return $path;
    }
}
