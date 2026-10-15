<?php namespace Seiger\sGallery\Controllers;

use Illuminate\Http\Request;
use Seiger\sGallery\Services\GallerySettings;
use Seiger\sGallery\Services\Watermark;

/** Save validated visual configuration without modifying vendor defaults. @since 1.7.0 */
class GallerySettingsController
{
    /** Clear owned derivatives only after settings permission and CSRF verification. @since 1.7.0 */
    public function clearCache(Request $request)
    {
        if (!evo()->hasPermission('settings') || empty($_SESSION['sgallery_settings_token']) || !hash_equals($_SESSION['sgallery_settings_token'], (string)$request->input('token'))) return response()->json(['error' => 'Недостатньо прав або недійсний токен.'], 403);
        try {
            $active = \Seiger\sGallery\Models\sGalleryModel::imageCacheDir();
            if (rtrim((string)$request->input('directory'), '/') . '/' !== $active) throw new \InvalidArgumentException('Спочатку збережи зміну каталогу кешу.');
            $count = \Seiger\sGallery\Services\GalleryCache::clear();
            if ($count > 0) evo()->clearCache();
            return response()->json(['count' => $count]);
        } catch (\Throwable $e) { return response()->json(['error' => $e->getMessage()], 422); }
    }

    /** Upload a uniquely named watermark without replacing existing images. @since 1.7.0 */
    public function upload(Request $request)
    {
        if (!evo()->hasPermission('settings') || empty($_SESSION['sgallery_settings_token']) || !hash_equals($_SESSION['sgallery_settings_token'], (string)$request->input('token'))) return response()->json(['error' => 'Недостатньо прав або недійсний токен.'], 403);
        try {
            $file = $request->file('file');
            $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'];
            if (!$file || !$file->isValid() || $file->getSize() > min((int)evo()->getConfig('upload_maxsize', 2097152), 20971520) || !isset($types[$file->getMimeType()]) || !@getimagesize($file->getPathname())) throw new \InvalidArgumentException('Завантаж файл зображення в межах дозволеного розміру.');
            $relative = 'assets/sgallery/watermarks/';
            $directory = EVO_BASE_PATH . $relative;
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new \RuntimeException('Не вдалося створити каталог.');
            $name = bin2hex(random_bytes(16)) . '.' . $types[$file->getMimeType()];
            $file->move($directory, $name);
            return response()->json(['file' => $relative . $name]);
        } catch (\Throwable $e) { return response()->json(['error' => $e->getMessage()], 422); }
    }

    /** Validate permissions, request token, templates and atomic file publication. @since 1.7.0 */
    public function save(Request $request)
    {
        if (!evo()->hasPermission('settings')) return response()->json(['error' => 'Недостатньо прав.'], 403);
        if (empty($_SESSION['sgallery_settings_token']) || !hash_equals($_SESSION['sgallery_settings_token'], (string)$request->input('token'))) return response()->json(['error' => 'Недійсний токен. Онови сторінку.'], 403);
        try {
            $input = json_decode((string)$request->input('settings'), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($input)) throw new \InvalidArgumentException('Invalid configuration.');
            $settings = GallerySettings::read();
            unset($settings['defaultWidth'], $settings['defaultHeight'], $settings['views'], $settings['optimizationBudgetSeconds']);
            $cache = trim((string)($input['imageCacheDir'] ?? ''));
            if (!preg_match('~^[a-zA-Z0-9_/-]+/$~D', $cache) || str_contains($cache, '..') || str_starts_with($cache, '/')) throw new \InvalidArgumentException('Каталог кешу: відносний шлях із завершальним /.');
            $settings['imageCacheDir'] = $cache;
            foreach (['quality' => [1, 100], 'sharpen' => [0, 100]] as $key => [$min, $max]) {
                if (!isset($input[$key]) || !is_numeric($input[$key]) || $input[$key] < $min || $input[$key] > $max) throw new \InvalidArgumentException('Некоректне значення: ' . $key);
                $settings[$key] = (int)$input[$key];
            }
            if (!in_array($input['format'] ?? '', ['avif', 'webp', 'original'], true)) throw new \InvalidArgumentException('Некоректний формат.');
            $settings['format'] = $input['format'];
            $settings['optimize'] = (bool)($input['optimize'] ?? false);
            $mark = (array)($input['watermark'] ?? []);
            $file = trim((string)($mark['file'] ?? ''));
            if ($file !== '') Watermark::path($file);
            unset($mark['file']);
            $settings['watermark'] = ['file' => $file] + Watermark::options($mark);
            $assignments = (array)($input['templates'] ?? []);
            $numeric = [];
            foreach ($assignments as $row) {
                $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id || !\EvolutionCMS\Models\SiteTemplate::where('id', $id)->exists() || isset($numeric[$id])) throw new \InvalidArgumentException('Некоректний або повторний шаблон ресурсу.');
                $blocks = array_values(array_filter(array_map('trim', explode(',', (string)($row['blocks'] ?? '')))));
                foreach ($blocks as $block) if (!preg_match('/^[\pL\pN_-]+$/uD', $block)) throw new \InvalidArgumentException('Некоректне ім’я блока.');
                $numeric[$id] = $blocks;
            }
            $settings = GallerySettings::withAssignments($settings, $numeric);
            $path = GallerySettings::path();
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true)) throw new \RuntimeException('Не вдалося створити каталог конфігурації.');
            $temp = tempnam(dirname($path), '.sgallery-');
            if (!$temp) throw new \RuntimeException('Не вдалося створити файл.');
            try {
                if (file_put_contents($temp, "<?php return " . GallerySettings::export($settings) . ";\n", LOCK_EX) === false || !chmod($temp, 0644) || !rename($temp, $path)) throw new \RuntimeException('Не вдалося записати конфігурацію.');
            } finally { if (is_file($temp)) unlink($temp); }
            if (function_exists('opcache_invalidate')) opcache_invalidate($path, true);
            config(['seiger.settings.sGallery' => $settings]);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
