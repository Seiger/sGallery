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
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }

use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\Services\GalleryCache;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Seiger\sGallery\Services\NativeImage;
use Seiger\sGallery\Services\Watermark;
use Seiger\sGallery\sGallery;

$container = new Illuminate\Container\Container();
Illuminate\Support\Facades\Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Accept the missing application binding when sTask is installed but not booted. @since 1.9.0 */
    public function warning(string $message): void {}
    /** Fail on actual image processing errors in this fixture. @since 1.9.0 */
    public function error(string $message): void { throw new RuntimeException($message); }
});

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
    try {
        (new sGallery())->file('photo.png')->watermark('missing.png');
        throw new RuntimeException('Missing explicit watermark accepted');
    } catch (InvalidArgumentException) {
        echo "PASS Missing explicit watermark rejected without Spatie\n";
    }
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
        checkOptionalImage(str_contains($error->getMessage(), 'source changed while queued'), 'Queued job with no source version is rejected before processing');
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
    foreach ([
        'crop' => (new sGalleryBuilder())->file('photo.png')->crop('right', 24),
        'focalCrop' => (new sGalleryBuilder())->file('photo.png')->focalCrop(50, 10, 24),
        'manualCrop' => (new sGalleryBuilder())->file('photo.png')->manualCrop(10, 10, 24),
    ] as $method => $crop) {
        $cropUrl = $crop->format('webp')->getFile();
        $cropPath = $root . rawurldecode(substr($cropUrl, strlen(EVO_SITE_URL)));
        checkOptionalImage(array_slice(getimagesize($cropPath), 0, 3) === [24, 24, IMAGETYPE_WEBP], "$method renders without Spatie");
    }
    $exif = "Exif\0\0II" . pack('vVv', 42, 8, 1) . pack('vvVVV', 0x0112, 3, 1, 6, 0);
    file_put_contents($root . 'oriented.jpg', substr($jpeg, 0, 2) . "\xff\xe1"
        . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));
    $orientedUrl = (new sGalleryBuilder())->file('oriented.jpg')->fit('max', 48)->format('webp')->getFile();
    $orientedPath = $root . rawurldecode(substr($orientedUrl, strlen(EVO_SITE_URL)));
    $expected = function_exists('exif_read_data') || class_exists(Imagick::class) ? [24, 48] : [48, 24];
    checkOptionalImage(array_slice(getimagesize($orientedPath), 0, 3) === [...$expected, IMAGETYPE_WEBP],
        'JPEG orientation without Spatie follows the available metadata reader');
    $flattenedUrl = (new sGalleryBuilder())->file('photo.png')->fit('max', 48)->format('jpg')->getFile();
    $flattened = $root . rawurldecode(substr($flattenedUrl, strlen(EVO_SITE_URL)));
    checkOptionalImage(array_slice(getimagesize($flattened), 0, 3) === [48, 24, IMAGETYPE_JPEG], 'Transparent PNG can produce a JPEG without Spatie');
    if (function_exists('imagecreatefromjpeg')) {
        $frame = imagecreatefromjpeg($flattened);
        $corner = imagecolorsforindex($frame, imagecolorat($frame, 0, 0));
    } else {
        $corner = (new Imagick($flattened))->getImagePixelColor(0, 0)->getColor();
    }
    checkOptionalImage(min(array_slice($corner, 0, 3)) > 235, 'JPEG flattens the transparent corner on white');

    $avif = (new sGalleryBuilder())->file('assets/images/photo.jpg')->format('avif')->getFile();
    $avifPath = $root . rawurldecode(substr($avif, strlen(EVO_SITE_URL)));
    $format = NativeImage::canEncode('avif', 'imagick') || NativeImage::canEncode('avif', 'gd') ? IMAGETYPE_AVIF : IMAGETYPE_WEBP;
    checkOptionalImage(array_slice(getimagesize($avifPath), 0, 3) === [96, 48, $format], 'A format-only AVIF request writes AVIF or WebP without Spatie');
    $derivatives = 9;
    if (NativeImage::canEncode('gif', 'imagick') || NativeImage::canEncode('gif', 'gd')) {
        $gif = (new sGalleryBuilder())->file('photo.png')->format('gif')->getFile();
        $gifPath = $root . rawurldecode(substr($gif, strlen(EVO_SITE_URL)));
        checkOptionalImage(array_slice(getimagesize($gifPath), 0, 3) === [96, 48, IMAGETYPE_GIF], 'A format-only GIF request writes GIF without Spatie');
        ++$derivatives;
    }
    checkOptionalImage(GalleryCache::clear() === $derivatives && file_get_contents($root . 'photo.png') === $png &&
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
