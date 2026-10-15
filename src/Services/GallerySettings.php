<?php namespace Seiger\sGallery\Services;

/** Resolve package configuration, template assignments and standard gallery views. @since 1.7.0 */
class GallerySettings
{
    /** Normalize simple IDs, flat named tabs and deprecated nested assignments. @since 1.7.0 */
    public static function assignments(array $settings): array
    {
        $assignments = [];
        foreach ($settings as $key => $value) {
            if (!is_int($key)) continue;
            if (is_int($value)) {
                $assignments[$value] = [];
            } elseif (is_array($value)) {
                if (count(array_filter($value, 'is_string')) === count($value)) {
                    $assignments[$key] = array_values($value);
                } else {
                    // Deprecated since 1.7.0: [[templateId => ['tab', ...]]].
                    foreach ($value as $id => $blocks) {
                        if (is_int($id) && is_array($blocks) && count(array_filter($blocks, 'is_string')) === count($blocks)) {
                            $assignments[$id] = array_values($blocks);
                        }
                    }
                }
            }
        }
        return $assignments;
    }

    /** Identify deprecated nested template assignments for an actionable manager notice. @since 1.7.0 */
    public static function hasLegacyAssignments(array $settings): bool
    {
        foreach ($settings as $key => $value) {
            if (is_int($key) && is_array($value)) {
                foreach ($value as $blocks) if (is_array($blocks)) return true;
            }
        }
        return false;
    }

    /** Write named assignments before simple IDs to avoid PHP numeric-key collisions. @since 1.7.0 */
    public static function withAssignments(array $settings, array $assignments): array
    {
        foreach (array_keys($settings) as $key) if (is_int($key)) unset($settings[$key]);
        $result = [];
        foreach ($assignments as $id => $blocks) if ($blocks !== []) $result[(int)$id] = array_values($blocks);
        foreach ($assignments as $id => $blocks) if ($blocks === []) $result[] = (int)$id;
        return $result + $settings;
    }

    /** Export configuration using short arrays and four-space nesting. @since 1.7.0 */
    public static function export(array $settings, int $depth = 0): string
    {
        if ($settings === []) return '[]';
        $indent = str_repeat('    ', $depth);
        $lines = [];
        $nextIndex = 0;
        foreach ($settings as $key => $value) {
            if (!is_array($value) && !is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Gallery settings must contain arrays and scalar values only.');
            }
            $formatted = is_array($value) ? self::export($value, $depth + 1) : var_export($value, true);
            $keyPrefix = is_int($key) && $key === $nextIndex ? '' : var_export($key, true) . ' => ';
            if (is_int($key)) $nextIndex = max($nextIndex, $key + 1);
            $lines[] = $indent . '    ' . $keyPrefix . $formatted . ',';
        }
        return "[\n" . implode("\n", $lines) . "\n" . $indent . ']';
    }

    /** Load shipped defaults when Evolution has no custom configuration. @since 1.7.0 */
    public static function defaults(): array
    {
        return require dirname(__DIR__, 2) . '/config/sGallerySettings.php';
    }

    /** Read the custom file when present, otherwise the shipped defaults. @since 1.7.0 */
    public static function read(): array
    {
        $path = self::path();
        $settings = is_file($path) ? require $path : self::defaults();
        return is_array($settings) ? $settings : [];
    }

    /** Return the only writable configuration destination. @since 1.7.0 */
    public static function path(): string
    {
        return EVO_BASE_PATH . 'core/custom/config/seiger/settings/sGallery.php';
    }

    /** Resolve the standard package Blade view. @since 1.7.0 */
    public static function view(string $name): string
    {
        return 'sGallery::' . $name;
    }
}
