<?php namespace Seiger\sGallery\Services;

use Spatie\Image\Image;
use Spatie\Image\Enums\AlignPosition;
use Spatie\Image\Enums\Unit;

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
     * Overlay after resizing and before encoding; never modify the source.
     * Resolve percentages to integer pixels before calling the image driver to avoid
     * implicit float-to-int conversions; truncate as the driver previously did.
     * @since 1.7.0
     */
    public static function apply(Image $image, array $mark): void
    {
        $path = self::path($mark['file']);
        if (hash_file('sha256', $path) !== $mark['hash']) throw new \RuntimeException('Watermark changed during processing.');
        $o = $mark['options'];
        $paddingPercent = str_ends_with((string)$o['padding'], '%');
        $paddingX = $paddingPercent ? (int)($image->getWidth() * (int)$o['padding'] / 100) : (int)$o['padding'];
        $paddingY = $paddingPercent ? (int)($image->getHeight() * (int)$o['padding'] / 100) : (int)$o['padding'];
        $width = str_ends_with((string)$o['width'], '%')
            ? max(1, (int)($image->getWidth() * (int)$o['width'] / 100))
            : (int)$o['width'];
        $image->watermark($path, position: AlignPosition::from($o['position']),
            paddingX: $paddingX, paddingY: $paddingY, paddingUnit: Unit::Pixel,
            width: $width, widthUnit: Unit::Pixel,
            alpha: $o['opacity']);
    }
}
