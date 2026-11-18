<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$loader = require getenv('PACKAGE_AUTOLOAD');
spl_autoload_unregister([$loader, 'loadClass']);
spl_autoload_register(static function (string $class) use ($loader): void {
    if (!str_starts_with($class, 'Spatie\\Image\\') && !str_starts_with($class, 'Spatie\\ImageOptimizer\\')) {
        $loader->loadClass($class);
    }
});

function config($key, $default = null) { return $default; }

use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\Services\GalleryCache;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Seiger\sGallery\Services\NativeImage;
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
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAGAAAAAwCAYAAADuFn/PAAAAZElEQVR4nO3RsQ3AIADAMM7pYfy/MbcXMLGgxpFygceQJEmSJN3dms/r/QAAtAcAoD0AAO0BAGgPAEB7AADaAwDQHgCA9gAAtAcAoD0AAO0BAGgPAEB7AADaA/g7gCRJkiTprA+lqRk8WsX9mAAAAABJRU5ErkJggg==', true);
file_put_contents($root . 'photo.png', $png);
file_put_contents($root . 'icon.svg', '<svg/>');
try {
    checkOptionalImage(!class_exists(Spatie\Image\Image::class) && !enum_exists(Spatie\Image\Enums\Fit::class) &&
        !class_exists(Spatie\ImageOptimizer\OptimizerChainFactory::class), 'Spatie processors and optimizers are unavailable');
    $manifest = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    checkOptionalImage(!isset($manifest['require']['spatie/image']) && isset($manifest['suggest']['spatie/image']), 'Image library is only suggested');
    foreach (['crop', 'contain', 'max', 'fill', 'stretch', 'fill-max', 'unknown'] as $fit) {
        $url = (string) (new sGallery())->file('photo.png')->fit($fit, 40, 20)->format('avif')->quality(85)->sharpen(10)->optimize(true)->watermark('missing.png');
        checkOptionalImage($url === EVO_SITE_URL . 'photo.png', "Fit {$fit} with unsupported options serves the original");
    }
    foreach (['topLeft', 'top', 'topRight', 'left', 'center', 'right', 'bottomLeft', 'bottom', 'bottomRight', 'unknown'] as $crop) {
        checkOptionalImage((new sGalleryBuilder())->file('photo.png')->crop($crop, 40)->getFile() === EVO_SITE_URL . 'photo.png', "Crop {$crop} serves original");
    }
    foreach (['crop', 'watermark', 'sharpen'] as $method) {
        $builder = (new sGallery())->file('photo.png')->resize(24)->format('webp');
        match ($method) {
            'crop' => $builder->crop('center', 24),
            'watermark' => $builder->watermark('missing.png'),
            'sharpen' => $builder->sharpen(20),
        };
        checkOptionalImage($builder->getFile() === EVO_SITE_URL . 'photo.png', "$method retains the original without Spatie");
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
    checkOptionalImage(!is_dir($root . 'assets'), 'Unsupported operations do not create a cache');

    $pngThumbnail = static fn (): string => (new sGallery())->file('photo.png')->resize(24)->format('webp')->getFile();
    $url = $pngThumbnail();
    if (!NativeImage::canEncode('webp', 'imagick') && !NativeImage::canEncode('webp', 'gd')) {
        checkOptionalImage($url === EVO_SITE_URL . 'photo.png', 'Native resize serves the original without a WebP codec');
        return;
    }

    checkOptionalImage($url !== EVO_SITE_URL . 'photo.png' && str_starts_with($url, EVO_SITE_URL),
        'Builder renders a PNG thumbnail without Spatie');
    $path = $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $info = getimagesize($path);
    checkOptionalImage(array_slice($info, 0, 3) === [48, 24, IMAGETYPE_WEBP], 'PNG thumbnail has the expected dimensions and WebP format');
    if (function_exists('imagecreatefromwebp')) {
        $frame = imagecreatefromwebp($path);
        $corner = imagecolorsforindex($frame, imagecolorat($frame, 0, 0));
        $center = imagecolorsforindex($frame, imagecolorat($frame, 24, 12));
        checkOptionalImage($corner['alpha'] > 120 && $center['alpha'] < 5, 'WebP preserves the transparent corner and opaque center');
    } else {
        $frame = new Imagick($path);
        $corner = $frame->getImagePixelColor(0, 0)->getColorValue(Imagick::COLOR_ALPHA);
        $center = $frame->getImagePixelColor(24, 12)->getColorValue(Imagick::COLOR_ALPHA);
        checkOptionalImage($corner < 0.03 && $center > 0.97, 'WebP preserves the transparent corner and opaque center');
    }
    unset($frame);

    mkdir($root . 'assets/images', 0755, true);
    $jpegPath = $root . 'assets/images/photo.jpg';
    if (function_exists('imagejpeg')) {
        $frame = imagecreatetruecolor(96, 48);
        imagejpeg($frame, $jpegPath);
    } else {
        $frame = new Imagick();
        $frame->newImage(96, 48, 'blue', 'JPEG');
        $frame->writeImage($jpegPath);
    }
    unset($frame);
    $jpeg = file_get_contents($jpegPath);
    $jpegThumbnail = static fn (): string => (new sGallery())->file('assets/images/photo.jpg')
        ->resize(48, 20)->format('webp')->optimize(false)->getFile();
    $jpegUrl = $jpegThumbnail();
    checkOptionalImage($jpegUrl !== EVO_SITE_URL . 'assets/images/photo.jpg' && str_starts_with($jpegUrl, EVO_SITE_URL),
        'Builder renders a JPEG thumbnail without Spatie');
    $jpegOutput = $root . rawurldecode(substr($jpegUrl, strlen(EVO_SITE_URL)));
    $info = getimagesize($jpegOutput);
    checkOptionalImage(array_slice($info, 0, 3) === [40, 20, IMAGETYPE_WEBP], 'JPEG thumbnail follows the existing resize geometry');

    foreach ([[$pngThumbnail, $url, $path], [$jpegThumbnail, $jpegUrl, $jpegOutput]] as [$render, $cachedUrl, $output]) {
        $savedAt = time() - 120;
        touch($output, $savedAt);
        clearstatcache(true, $output);
        checkOptionalImage($render() === $cachedUrl && filemtime($output) === $savedAt, 'A repeat request reuses the cached WebP');
    }
    $fitUrl = (new sGallery())->file('photo.png')->fit('max', 24)->format('webp')->getFile();
    $fitOutput = $root . rawurldecode(substr($fitUrl, strlen(EVO_SITE_URL)));
    checkOptionalImage($fitOutput !== $path && array_slice(getimagesize($fitOutput), 0, 3) === [24, 12, IMAGETYPE_WEBP],
        'Native fit generates a separate derivative from plain resize');
    checkOptionalImage(GalleryCache::clear() === 3 && file_get_contents($root . 'photo.png') === $png &&
        file_get_contents($jpegPath) === $jpeg, 'Cache clearing removes the derivatives and preserves their sources');
} finally {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
