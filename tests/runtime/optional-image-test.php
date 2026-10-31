<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$loader = require getenv('PACKAGE_AUTOLOAD');
spl_autoload_unregister([$loader, 'loadClass']);
spl_autoload_register(static function (string $class) use ($loader): void {
    if (!str_starts_with($class, 'Spatie\\Image\\')) {
        $loader->loadClass($class);
    }
});

function config($key, $default = null) { return $default; }

use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Seiger\sGallery\Services\Watermark;
use Seiger\sGallery\sGallery;

function checkOptionalImage(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS {$message}\n";
}

$root = sys_get_temp_dir() . '/sgallery-optional-image-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0755, true);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
file_put_contents($root . 'photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aE1sAAAAASUVORK5CYII='));
file_put_contents($root . 'icon.svg', '<svg/>');
try {
    checkOptionalImage(!class_exists(Spatie\Image\Image::class) && !enum_exists(Spatie\Image\Enums\Fit::class), 'Spatie classes and enums are unavailable');
    $manifest = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    checkOptionalImage(!isset($manifest['require']['spatie/image']) && isset($manifest['suggest']['spatie/image']), 'Image library is only suggested');
    foreach (['crop', 'contain', 'max', 'fill', 'stretch', 'fill-max', 'unknown'] as $fit) {
        $url = (string) (new sGallery())->file('photo.png')->fit($fit, 40, 20)->format('avif')->quality(85)->sharpen(10)->optimize(true)->watermark('missing.png');
        checkOptionalImage($url === EVO_SITE_URL . 'photo.png', "Fit {$fit} serves original without image library");
    }
    foreach (['topLeft', 'top', 'topRight', 'left', 'center', 'right', 'bottomLeft', 'bottom', 'bottomRight', 'unknown'] as $crop) {
        checkOptionalImage((new sGalleryBuilder())->file('photo.png')->crop($crop, 40)->getFile() === EVO_SITE_URL . 'photo.png', "Crop {$crop} serves original");
    }
    checkOptionalImage((string) (new sGalleryBuilder())->file('photo.png')->focalCrop(50, 50, 40)->manualCrop(0, 0, 20, 20)->resize(10) === EVO_SITE_URL . 'photo.png', 'Other transformations serve original');
    foreach (['https://remote.test/photo.jpg', 'http://remote.test/photo.jpg'] as $remote) {
        checkOptionalImage((new sGalleryBuilder())->file($remote)->fit('crop', 20)->getFile() === $remote, 'Remote original returned without downloading');
    }
    checkOptionalImage((string) (new sGalleryBuilder())->file('missing.png')->fit('max', 20) === sGalleryModel::NOIMAGE, 'Missing source retains placeholder');
    checkOptionalImage((string) (new sGalleryBuilder())->file('icon.svg')->resize(20) === EVO_SITE_URL . 'icon.svg', 'SVG remains accessible');
    checkOptionalImage(Watermark::options([])['position'] === 'bottom-right', 'Default watermark settings validate without image library');
    checkOptionalImage(Watermark::path('photo.png') === realpath($root . 'photo.png'), 'Watermark path validates without image library');
    try {
        Watermark::options(['position' => 'invalid']);
        throw new RuntimeException('Invalid watermark position accepted');
    } catch (ValueError $error) {
        echo "PASS invalid watermark position rejected\n";
    }
    $queue = new ImageOptimizationQueue();
    checkOptionalImage(!$queue->available() && !$queue->defer([]), 'No optimization job or marker created');
    try {
        (new sGalleryBuilder())->processOptimization(['file' => 'photo.png', 'params' => ['fit' => 'crop']]);
        throw new RuntimeException('Queued optimization unexpectedly succeeded');
    } catch (RuntimeException $error) {
        checkOptionalImage(str_contains($error->getMessage(), 'optional spatie/image'), 'Existing queued job fails with actionable exception');
    }
    checkOptionalImage(count(scandir($root)) === 4, 'Original files remain untouched and no cache is created');
} finally {
    unlink($root . 'photo.png');
    unlink($root . 'icon.svg');
    rmdir($root);
}
