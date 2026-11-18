<?php namespace Seiger\sGallery\Services;

use Seiger\sGallery\Models\sGalleryModel;

/** Track owned image derivatives so cache clearing cannot remove original media. @since 1.7.0 */
class GalleryCache
{
    /** Map a local source to the existing relative derivative directory. @since 1.9.0 */
    public static function directoryFor(string $source): string
    {
        $directory = sGalleryModel::imageCacheDir();
        foreach (explode('/', str_replace('\\', '/', pathinfo($source, PATHINFO_DIRNAME))) as $segment) {
            if ($segment !== '.') {
                $directory .= is_numeric($segment) ? $segment : ($segment[0] ?? '');
            }
        }
        return rtrim($directory, '/') . '/';
    }

    /**
     * Record generated or reused derivatives without changing their source.
     * Callers may reuse a just-completed file validation. Missing index directories are
     * repaired after write failure, including external removal in a long-running process.
     * @param string $source Original media path.
     * @param string $output Owned derivative path inside the configured cache.
     * @param bool $validated Whether this caller has just validated the derivative file.
     * @return void
     * @since 1.7.0
     */
    public static function remember(string $source, string $output, bool $validated = false): void
    {
        $cache = sGalleryModel::imageCacheDir();
        if ($source === $output || !str_starts_with($output, $cache) ||
            (!$validated && GalleryFiles::local($output) === null)) return;
        $directory = GalleryFiles::directory($cache . '.sgallery-index', true);
        if ($directory === null) return;
        $marker = $directory . DIRECTORY_SEPARATOR . hash('sha256', $output) . '.json';
        if (!is_link($marker) && !is_file($marker)) {
            @file_put_contents($marker, json_encode(['source' => $source, 'output' => $output], JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }

    /** Remove only indexed derivatives within the active cache, excluding all recorded sources. @since 1.7.0 */
    public static function clear(): int
    {
        $relative = sGalleryModel::imageCacheDir();
        $site = realpath(EVO_BASE_PATH);
        $root = realpath(EVO_BASE_PATH . $relative);
        if (!$root) return 0;
        if (!$site || $root === $site || !str_starts_with($root, $site . DIRECTORY_SEPARATOR)) throw new \RuntimeException('Invalid gallery cache directory.');
        $index = $root . DIRECTORY_SEPARATOR . '.sgallery-index';
        if (is_link($index)) throw new \RuntimeException('Cache index must not be a symlink.');
        $entries = []; $sources = [];
        foreach (glob($index . '/*.json') ?: [] as $marker) {
            if (is_link($marker)) continue;
            $entry = json_decode((string)file_get_contents($marker), true);
            if (!is_array($entry) || !isset($entry['source'], $entry['output'])) continue;
            $source = realpath(EVO_BASE_PATH . $entry['source']);
            if ($source) $sources[$source] = true;
            $entries[$marker] = $entry['output'];
        }
        $count = 0;
        foreach ($entries as $marker => $output) {
            $candidate = EVO_BASE_PATH . $output;
            $path = realpath($candidate);
            if ($path && !is_link($candidate) && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && !isset($sources[$path]) && is_file($path)) {
                if (!unlink($path)) throw new \RuntimeException('Cannot remove cached image.');
                $count++;
            }
            if (!$path || !is_file($path)) unlink($marker);
        }
        return $count;
    }
}
