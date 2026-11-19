<?php
declare(strict_types=1);

namespace Seiger\sGallery\Services {
    /** Replace the source after decoding but before the candidate reaches the Builder. @since 1.9.0 */
    function rename(string $from, string $to): bool
    {
        $result = \rename($from, $to);
        if ($result && isset($GLOBALS['sourceDuringEncode']) && str_contains($to, '.pending-')) {
            $callback = $GLOBALS['sourceDuringEncode'];
            unset($GLOBALS['sourceDuringEncode']);
            $callback();
        }
        return $result;
    }
}
namespace Seiger\sGallery\Builders {
    /** Exercise replacement immediately before and after final publication without timing sleeps. @since 1.9.0 */
    function rename(string $from, string $to): bool
    {
        if (isset($GLOBALS['sourceAtPublish']) && !str_contains($to, '.pending-')) {
            [$when, $callback] = $GLOBALS['sourceAtPublish'];
            unset($GLOBALS['sourceAtPublish']);
            if ($when === 'before') $callback();
            $result = \rename($from, $to);
            if ($when === 'after') $callback();
            return $result;
        }
        return \rename($from, $to);
    }
}
namespace {
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\GalleryCache;
use Seiger\sGallery\Services\NativeImage;
use Spatie\Image\Image;

function config($key, $default = null) { return $key === 'seiger.settings.sGallery.optimize' ? false : $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }
function evo() { return new class {
    /** Isolate queue discovery from a real site; the worker replay uses descriptors directly. @since 1.9.0 */
    public function getConfig($key, $default = null) { return $default; }
}; }
require __DIR__ . '/bootstrap.php';
$withSpatie = class_exists(Image::class);
printf("PHP %s, Spatie %s, GD %s, Imagick %s\n", PHP_VERSION, $withSpatie ? 'on' : 'off', extension_loaded('gd') ? 'on' : 'off', class_exists(Imagick::class) ? 'on' : 'off');
$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Preserve expected race failures for assertion diagnostics. @since 1.9.0 */
    public function error(string $message): void { $GLOBALS['sourceErrors'][] = $message; }
    /** Accept unavailable optional queue discovery in the isolated fixture. @since 1.9.0 */
    public function warning(string $message): void {}
});
$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label . ' ' . json_encode($GLOBALS['sourceErrors'] ?? []));
    echo "PASS $label\n";
};
if (!NativeImage::canEncode('png', 'gd') && !NativeImage::canEncode('png', 'imagick')) {
    echo "SKIP source/cache races require a PNG codec\n";
    return;
}
$root = sys_get_temp_dir() . '/sgallery-source-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
$chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
// Uncompressed IDAT streams keep every color variant exactly the same byte length.
$png = static fn (array $rgb): string => "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 32, 16, 8, 2, 0, 0, 0)) .
    $chunk('IDAT', gzcompress(str_repeat("\0" . str_repeat(pack('C*', ...$rgb), 32), 16), 0)) . $chunk('IEND', '');
$red = $png([220, 20, 30]);
$blue = $png([10, 40, 230]);
$green = $png([20, 210, 40]);
$replace = static function (string $source, string $bytes, int $mtime = 1700000000): void {
    file_put_contents(EVO_BASE_PATH . $source, $bytes);
    touch(EVO_BASE_PATH . $source, $mtime);
    clearstatcache(true, EVO_BASE_PATH . $source);
};
$build = static fn (string $source, int $size = 16, string $format = 'png'): sGalleryBuilder =>
    (new sGalleryBuilder())->file($source)->fit('max', $size)->format($format)->quality(90)->optimize(false);
$descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
$pixel = static function (string $file): array {
    $frame = NativeImage::open(rawurldecode($file), 'png')->frame();
    if ($frame instanceof Imagick) {
        $color = $frame->getImagePixelColor(1, 1)->getColor();
        return [$color['r'], $color['g'], $color['b']];
    }
    $color = imagecolorsforindex($frame, imagecolorat($frame, 1, 1));
    return [$color['red'], $color['green'], $color['blue']];
};
$reject = static function (array $job, string $message) use ($check): void {
    try { (new sGalleryBuilder())->processOptimization($job); }
    catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(), $message), 'Worker rejects stale source: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('A stale job unexpectedly finished');
};
try {
    $check(strlen($red) === strlen($blue), 'Replacement fixtures have equal byte lengths');
    $replace('source.png', $red);
    $image = $build('source.png');
    $first = $image->getFile();
    $oldHash = hash_file('sha256', $path($first));
    $oldJob = $descriptor->invoke($image, 'png');
    touch($path($first), 1600000000);
    $check($image->getFile() === $first && filemtime($path($first)) === 1600000000, 'Unchanged source reuses its derivative');
    $replace('source.png', $blue);
    $updated = $image->getFile();
    $check($updated !== $first && $pixel(substr($updated, strlen(EVO_SITE_URL))) === [10, 40, 230],
        'Same-size, same-mtime replacement returns new pixels and a new URL on a reused Builder');
    $check(count($oldJob['source_version']) === 3, 'New descriptors pin a content hash as well as size and mtime');
    $check(hash_file('sha256', $path($first)) === $oldHash, 'Replacement preserves the earlier valid cache');
    $reject($oldJob, 'source changed while queued');
    $check($image->getFile() === $updated, 'Rejected old job cannot replace the current derivative');
    $replace('source.png', $green, 1700000060);
    $newer = $build('source.png')->getFile();
    $check($newer !== $updated && $pixel(substr($newer, strlen(EVO_SITE_URL))) === [20, 210, 40], 'Changed-mtime replacement also refreshes cache');

    // Distinct paths must not share a key even if GalleryCache abbreviates their directories.
    mkdir($root . 'alpha'); mkdir($root . 'another');
    $replace('alpha/photo.png', $red); $replace('another/photo.png', $blue);
    $a = $build('alpha/photo.png')->getFile(); $b = $build('another/photo.png')->getFile();
    $check($a !== $b && $pixel(substr($a, strlen(EVO_SITE_URL))) === [220, 20, 30] && $pixel(substr($b, strlen(EVO_SITE_URL))) === [10, 40, 230],
        'Full source paths disambiguate equal basenames in abbreviated cache directories');
    $long = str_repeat('ф', 100) . '.png';
    $replace($long, $blue);
    $url = $build($long)->getFile();
    $check(str_starts_with($url, EVO_SITE_URL . 'assets/cache/') && strlen(basename($path($url))) <= 220 && preg_match('//u', basename($path($url))) === 1 &&
        $pixel(substr($url, strlen(EVO_SITE_URL))) === [10, 40, 230],
        'Versioned cache names fit the filesystem limit without cutting a UTF-8 character');
    $rgba = static fn (int $alpha): string => "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 32, 16, 8, 6, 0, 0, 0)) .
        $chunk('IDAT', gzcompress(str_repeat("\0" . str_repeat(pack('C*', 220, 20, 30, $alpha), 32), 16), 0)) . $chunk('IEND', '');
    $_SESSION = []; $_COOKIE = [];
    unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_SEC_CH_ACCEPT'], $_SERVER['HTTP_USER_AGENT']);
    $replace('metadata.png', $rgba(255));
    $metadata = (new sGalleryBuilder())->file('metadata.png')->fit('max', 16)->optimize(false);
    $opaque = $metadata->getFile();
    $replace('metadata.png', $rgba(0));
    $transparent = $metadata->getFile();
    $fresh = (new sGalleryBuilder())->file('metadata.png')->fit('max', 16)->optimize(false);
    $check($opaque !== $transparent && $fresh->getFile() === $transparent, 'A reused Builder follows the current auto-format policy after replacement');
    if (!$withSpatie || function_exists('imagecreatefrompng')) {
        $check((new ReflectionProperty($metadata, 'sourceHasAlpha'))->getValue($metadata) === true,
            'The reused Builder discards its earlier opaque-source metadata');
    }
    if (!$withSpatie) {
        $frame = NativeImage::open(rawurldecode(substr($transparent, strlen(EVO_SITE_URL))), 'png')->frame();
        $opacity = $frame instanceof Imagick ? $frame->getImagePixelColor(1, 1)->getColorValue(Imagick::COLOR_ALPHA)
            : 1 - imagecolorsforindex($frame, imagecolorat($frame, 1, 1))['alpha'] / 127;
        $check(getimagesize($path($transparent))[2] === IMAGETYPE_PNG && $opacity < 0.01, 'Native auto format retains the replacement alpha');
    }

    $replace('legacy.png', $red);
    $legacy = $descriptor->invoke($build('legacy.png', 13), 'png');
    $legacy['source_version'] = array_slice($legacy['source_version'], 0, 2);
    $url = (new sGalleryBuilder())->processOptimization($legacy);
    $check($build('legacy.png', 13)->getFile() === $url, 'An unchanged earlier two-field descriptor shares the current cache');
    $replace('legacy.png', $blue);
    $url = (new sGalleryBuilder())->processOptimization($legacy);
    $check($pixel(substr($url, strlen(EVO_SITE_URL))) === [10, 40, 230], 'A legacy descriptor with equal old stat data renders current bytes');
    $replace('legacy.png', $green, 1700000060);
    $reject($legacy, 'source changed while queued');

    foreach (['encode', 'before', 'after'] as $race) {
        foreach (['png', 'webp'] as $format) {
            if (!NativeImage::canEncode($format, 'gd') && !NativeImage::canEncode($format, 'imagick')) continue;
            $source = "$race-$format.png";
            $replace($source, $red);
            $previous = $build($source, 8, $format)->getFile();
            $previousHash = hash_file('sha256', $path($previous));
            $unpublished = $build($source, 15, $format)->getFile();
            unlink($path($unpublished));
            $job = $descriptor->invoke($build($source, 15, $format), $format);
            $currentUrl = null;
            $currentHash = null;
            $callback = static function () use ($replace, $source, $blue, $build, $format, &$currentUrl, &$currentHash, $path): void {
                $replace($source, $blue);
                $currentUrl = $build($source, 15, $format)->getFile();
                $currentHash = hash_file('sha256', $path($currentUrl));
            };
            if ($race === 'encode') $GLOBALS['sourceDuringEncode'] = $callback;
            else $GLOBALS['sourceAtPublish'] = [$race, $callback];
            $reject($job, 'source changed during optimization');
            $check(!is_file($path($unpublished)), "$format $race race leaves no stale published result");
            $check($currentUrl !== null && $build($source, 15, $format)->getFile() === $currentUrl && hash_file('sha256', $path($currentUrl)) === $currentHash,
                "$format $race race preserves the newer derivative");
            $check(hash_file('sha256', $path($previous)) === $previousHash, "$format $race race preserves the earlier valid cache");
        }
    }
    $replace('frontend.png', $red);
    $GLOBALS['sourceDuringEncode'] = static fn () => $replace('frontend.png', $blue);
    $check($build('frontend.png')->getFile() === EVO_SITE_URL . 'frontend.png', 'A frontend race returns the current original instead of a stale derivative');
    $front = $build('frontend.png')->getFile();
    $check($pixel(substr($front, strlen(EVO_SITE_URL))) === [10, 40, 230], 'The next frontend call renders the replacement');
    $check(file_get_contents($root . 'source.png') === $green, 'The pipeline never modifies its source');
    $pending = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_contains($file->getFilename(), '.pending-') || str_starts_with($file->getFilename(), '.sgallery-image-')) $pending[] = $file->getPathname();
    }
    $check($pending === [], 'Source races leave no abandoned staged files');
    $check(GalleryCache::clear() > 0 && is_file($root . 'source.png'), 'Indexed cleanup preserves sources across revisions');
} finally {
    unset($GLOBALS['sourceAtPublish'], $GLOBALS['sourceDuringEncode']);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
}
