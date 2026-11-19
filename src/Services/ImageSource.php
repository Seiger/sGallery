<?php namespace Seiger\sGallery\Services;

/** Capture a local source revision without decoding it or loading its bytes into memory. @since 1.9.0 */
final class ImageSource
{
    /**
     * Hash the contents and reject a file replaced while its revision was being read.
     * @return array{int, int, string}|null Size, modification time and SHA-256.
     * @since 1.9.0
     */
    public static function version(string $path): ?array
    {
        clearstatcache(true, $path);
        if (is_link($path) || !is_file($path)) return null;
        $before = @stat($path);
        $hash = @hash_file('sha256', $path);
        clearstatcache(true, $path);
        $after = @stat($path);
        if ($before === false || $after === false || $hash === false || is_link($path)) return null;
        foreach (['dev', 'ino', 'size', 'mtime', 'ctime'] as $field) {
            if ($before[$field] !== $after[$field]) return null;
        }
        return [$after['size'], $after['mtime'], $hash];
    }

    /** Validate earlier two-field sTask descriptors before pinning the current full revision. @since 1.9.0 */
    public static function matchesQueued(mixed $queued, ?array $current): bool
    {
        return $current !== null && is_array($queued) &&
            ($queued === $current || (count($queued) === 2 && $queued === array_slice($current, 0, 2)));
    }
}
