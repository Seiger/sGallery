<?php namespace Seiger\sGallery\Services;

use RuntimeException;
use Seiger\sGallery\Models\sGalleryModel;

/**
 * Resolve site files and keep uploads, shared library files and cache files within their roots.
 *
 * @since 1.8.0
 */
final class GalleryFiles
{
    /** Validate a logical gallery type or block as one filesystem-safe identifier. @since 1.8.0 */
    public static function identifier(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[\pL\pN_-]{1,64}\z/u', $value) === 1;
    }

    /** Resolve a positive integer without coercing arrays, floats or out-of-range values. @since 1.8.0 */
    public static function id(mixed $value): ?int
    {
        if (!is_int($value) && (!is_string($value) || !ctype_digit($value))) {
            return null;
        }

        $id = filter_var(is_string($value) ? ltrim($value, '0') : $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    /** Accept a site-relative path with plain, nonempty segments. @since 1.8.0 */
    public static function relative(string $path): bool
    {
        if ($path === '' || strpbrk($path, '\\:*?"<>|') !== false || preg_match('~[\x00-\x1F\x7F]~', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || rtrim($segment, '. ') !== $segment) {
                return false;
            }
        }

        return true;
    }

    /**
     * Walk from the canonical site root. Reject symlinks and directory aliases
     * before creating a child. New directories inherit the process umask.
     * @since 1.8.0
     */
    public static function directory(string $relative, bool $create = false): ?string
    {
        $relative = rtrim($relative, '/');
        $root = realpath(EVO_BASE_PATH);
        if ($root === false || !self::relative($relative)) {
            return null;
        }

        $directory = $root;
        foreach (explode('/', $relative) as $segment) {
            $directory .= DIRECTORY_SEPARATOR . $segment;
            if ($create && !file_exists($directory) && !is_link($directory)) {
                @mkdir($directory, 0755);
            }
            if (!is_dir($directory) || is_link($directory) || realpath($directory) !== $directory) {
                return null;
            }
        }

        return $directory;
    }

    /** Resolve an existing regular, readable site file without following path aliases. @since 1.8.0 */
    public static function local(string $relative): ?string
    {
        if (!self::relative($relative)) {
            return null;
        }

        $parent = dirname($relative);
        $directory = $parent === '.' ? realpath(EVO_BASE_PATH) : self::directory($parent);
        if (!$directory) {
            return null;
        }
        $path = $directory . DIRECTORY_SEPARATOR . basename($relative);
        return !is_link($path) && is_file($path) && is_readable($path) && realpath($path) === $path ? $path : null;
    }

    /** Determine the relative directory for files owned by this gallery parent. @since 1.8.0 */
    public static function uploadDirectory(string $type, int $parent): string
    {
        if (!self::identifier($type) || $parent < 1) {
            throw new RuntimeException('Invalid gallery location.');
        }
        return 'assets/sgallery/' . $type . '/' . $parent;
    }

    /** Resolve an owned legacy or current filename using the persisted parent and type. @since 1.8.0 */
    public static function owned(sGalleryModel $gallery): ?string
    {
        $file = (string) $gallery->file;
        $parent = self::id($gallery->parent);
        $type = (string) $gallery->item_type;
        if ($gallery->type === sGalleryModel::TYPE_YOUTUBE || $parent === null || !self::identifier($type) ||
            !self::relative($file) || str_contains($file, '/')) {
            return null;
        }

        $path = self::uploadDirectory($type, $parent) . '/' . $file;
        return self::local($path) !== null ? $path : null;
    }

    /**
     * Resolve a shared path within the configured browser root.
     * Registration also honors the narrower per-user image directory.
     * @since 1.8.0
     */
    public static function library(string $file, bool $register = false): ?string
    {
        if (!str_contains($file, '/') || ($register && str_starts_with($file, 'assets/sgallery/'))) {
            return null;
        }

        $root = (string) evo()->getConfig('rb_base_dir', 'assets/');
        $root = self::relativeRoot($root === '' ? 'assets/' : $root);
        if ($root === null) {
            return null;
        }
        if ($register && ($custom = (string) evo()->getConfig('image_base_upload_dir', '')) !== '') {
            $custom = str_replace('\\', '/', $custom);
            $absolute = str_starts_with($custom, '/') || str_starts_with($custom, '[(base_path)]') ||
                preg_match('~\A[A-Za-z]:/~', $custom);
            $custom = self::relativeRoot($absolute ? $custom : $root . '/' . $custom);
            if ($custom === null || ($custom !== $root && !str_starts_with($custom, $root . '/'))) {
                return null;
            }
            $root = $custom;
        }

        return $root !== null && str_starts_with($file, $root . '/') && self::local($file) !== null ? $file : null;
    }

    /** Convert an in-site browser root to relative form. Reject outside-site roots. @since 1.8.0 */
    private static function relativeRoot(string $root): ?string
    {
        $base = rtrim(str_replace('\\', '/', EVO_BASE_PATH), '/') . '/';
        $root = str_replace('\\', '/', str_replace('[(base_path)]', $base, $root));
        if (str_starts_with($root, $base)) {
            $root = substr($root, strlen($base));
        }
        $root = rtrim($root, '/');
        return self::relative($root) ? $root : null;
    }

    /** Encode path segments when exposing a resolved file in a URL. @since 1.8.0 */
    public static function url(string $relative): string
    {
        return rtrim(EVO_SITE_URL, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
    }
}
