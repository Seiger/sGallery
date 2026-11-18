<?php namespace Seiger\sGallery\Services;

/**
 * Estimate the memory needed for raster operations before calling a codec.
 *
 * @internal
 * @since 1.9.0
 */
final class ImageMemoryBudget
{
    private const WEB_FALLBACK_LIMIT = '128M';
    private const WORKER_FALLBACK_LIMIT = '512M';
    private const RUNTIME_RESERVE = 16 * 1024 * 1024;
    private const BYTES_PER_PIXEL = 16;

    /**
     * Compare raster buffers and compressed input against the process budget.
     *
     * Web requests use half of the available headroom. The estimate reserves multiple
     * buffers per raster for codec overhead and transformations. When PHP has no
     * configured memory cap, use a finite estimate without changing the runtime setting.
     * Floats let very large dimensions exceed the budget without integer overflow.
     *
     * @param float $pixels Combined pixel count across the rasters involved.
     * @param int $fileBytes Compressed input size in bytes.
     * @param bool $background Whether the operation can use the worker budget.
     * @since 1.9.0
     */
    public static function allows(float $pixels, int $fileBytes, bool $background = false): bool
    {
        $bytes = ini_parse_quantity((string) ini_get('memory_limit'));
        if ($bytes <= 0) {
            $bytes = ini_parse_quantity($background ? self::WORKER_FALLBACK_LIMIT : self::WEB_FALLBACK_LIMIT);
        }

        $headroom = $bytes - memory_get_usage(true) - self::RUNTIME_RESERVE;
        $available = $background ? $headroom : $headroom / 2;

        return $pixels * self::BYTES_PER_PIXEL + $fileBytes <= $available;
    }
}
