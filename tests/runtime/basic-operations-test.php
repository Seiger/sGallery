<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\Services\NativeImage;
use Spatie\Image\Image;

function config($key, $default = null) { return $GLOBALS['basicSettings'][$key] ?? $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }
function evo()
{
    return new class {
        /** Keep optional queue discovery isolated from a real CMS site. @since 1.9.0 */
        public function getConfig($key, $default = null) { return $default; }
    };
}

require __DIR__ . '/bootstrap.php';
$withSpatie = class_exists(Image::class);
printf("PHP %s, Spatie %s, GD %s, Imagick %s\n", PHP_VERSION, $withSpatie ? 'on' : 'off', extension_loaded('gd') ? 'on' : 'off', extension_loaded('imagick') ? 'on' : 'off');
$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Preserve codec diagnostics for assertions, including expected missing-driver failures. @since 1.9.0 */
    public function error(string $message): void { $GLOBALS['basicErrors'][] = $message; }
    /** Accept unavailable AVIF encoders and an unbooted optional sTask service. @since 1.9.0 */
    public function warning(string $message): void {}
});
$basicSettings = [
    'seiger.settings.sGallery.quality' => 83,
    'seiger.settings.sGallery.optimize' => false,
    'seiger.settings.sGallery.format' => 'auto',
];
$root = sys_get_temp_dir() . '/sgallery-basic-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label . ' ' . json_encode($GLOBALS['basicErrors'] ?? []));
    echo "PASS $label\n";
};
$path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
$canEncode = static fn (string $format): bool => NativeImage::canEncode($format, 'gd') || NativeImage::canEncode($format, 'imagick');
$cases = [
    'resize-auto' => [static fn ($b) => $b->resize(16, 8), 16, 8, null],
    'resize-one-argument' => [static fn ($b) => $b->resize(16), 32, 16, null],
    'resize-png' => [static fn ($b) => $b->resize(16, 8)->format('png'), 16, 8, 'png'],
    'resize-jpeg' => [static fn ($b) => $b->resize(16, 8)->format('jpeg'), 16, 8, 'jpeg'],
    'resize-square-request' => [static fn ($b) => $b->resize(16, 16)->format('png'), 32, 16, 'png'],
    'resize-original' => [static fn ($b) => $b->resize(16, 8)->format('original'), 16, 8, null],
    'format-png' => [static fn ($b) => $b->format('png'), 32, 16, 'png'],
    'format-jpg' => [static fn ($b) => $b->format('jpg'), 32, 16, 'jpeg'],
    'format-jpeg' => [static fn ($b) => $b->format('jpeg'), 32, 16, 'jpeg'],
    'format-webp' => [static fn ($b) => $b->format('webp'), 32, 16, 'webp'],
    'format-gif' => [static fn ($b) => $b->format('gif'), 32, 16, 'gif'],
    'format-avif' => [static fn ($b) => $b->format('avif'), 32, 16, 'avif'],
];

try {
    copy(__DIR__ . '/../fixtures/heif-rgba-source.png', $root . 'alpha.png');
    $sources = ['alpha.png' => 'png'];
    if ($canEncode('jpeg')) {
        NativeImage::open('alpha.png', 'jpeg')->save('opaque.jpeg', 90);
        $sources['opaque.jpeg'] = 'jpeg';
    }
    foreach ($sources as $source => $sourceFormat) {
        $original = EVO_SITE_URL . $source;
        $hash = hash_file('sha256', $root . $source);
        foreach ($cases as $name => [$operation, $width, $height, $format]) {
            $render = static function () use ($source, $operation): string {
                $_SESSION = [];
                $_COOKIE = [];
                unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_SEC_CH_ACCEPT'], $_SERVER['HTTP_USER_AGENT']);
                return $operation((new sGalleryBuilder())->file($source))->getFile();
            };
            $expectedFormat = $format ?? $sourceFormat;
            if ($withSpatie && $format === null && $name !== 'resize-original') {
                // Keep the legacy driver's format selection, including its behavior without GD.
                $_SESSION = [];
                $_COOKIE = [];
                unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_SEC_CH_ACCEPT'], $_SERVER['HTTP_USER_AGENT']);
                $auto = (new sGalleryBuilder())->file($source)->getSupportedImageFormat();
                $expectedFormat = $auto === 'jpg' ? 'jpeg' : $auto;
            }
            $url = $render();
            if ($expectedFormat === 'avif' && $canEncode('webp') && (!$canEncode('avif') || str_ends_with($url, '.webp'))) $expectedFormat = 'webp';
            if (!$canEncode($expectedFormat)) {
                $check($url === ($withSpatie ? sGalleryModel::NOIMAGE : $original), "$source $name retains its no-codec fallback");
                continue;
            }
            $info = $url !== $original && str_starts_with($url, EVO_SITE_URL) ? @getimagesize($path($url)) : false;
            $check($info !== false && array_slice($info, 0, 2) === [$width, $height] && $info['mime'] === 'image/' . $expectedFormat,
                "$source $name renders $width x $height $expectedFormat ($url)");
            touch($path($url), 1700000000);
            clearstatcache(true, $path($url));
            $check($render() === $url && filemtime($path($url)) === 1700000000, "$source $name reuses its cache");
            if (!$withSpatie && $expectedFormat === 'jpeg' && $sourceFormat === 'png') {
                $frame = NativeImage::open(substr($url, strlen(EVO_SITE_URL)), 'jpeg')->frame();
                $pixel = $frame instanceof Imagick ? $frame->getImagePixelColor((int) ($width * 0.75), (int) ($height * 0.75))->getColor()
                    : imagecolorsforindex($frame, imagecolorat($frame, (int) ($width * 0.75), (int) ($height * 0.75)));
                $check(min($pixel['r'] ?? $pixel['red'], $pixel['g'] ?? $pixel['green'], $pixel['b'] ?? $pixel['blue']) > 230,
                    "$name composites transparent PNG pixels on white");
            }
        }
        if (!$withSpatie) {
            $check((new sGalleryBuilder())->file($source)->getFile() === $original, "$source without an operation remains the original");
            $check((new sGalleryBuilder())->file($source)->format('original')->getFile() === $original, "$source original-only request stays unchanged");
        }
        $check(hash_file('sha256', $root . $source) === $hash, "$source bytes remain unchanged");
    }
    // Build the job before the frontend has rendered this operation; quality stays implicit.
    $image = (new sGalleryBuilder())->file('alpha.png')->resize(10, 10)->format('png');
    $descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
    $job = $descriptor->invoke($image, 'png');
    $check($job['quality'] === 83 && !$job['quality_explicit'], 'Background replay retains configured, implicit quality');
    if ($canEncode('png')) {
        $queued = (new sGalleryBuilder())->processOptimization($job);
        $check(array_slice(getimagesize($path($queued)), 0, 3) === [20, 10, IMAGETYPE_PNG] && $image->getFile() === $queued,
            'Cold background resize and frontend use the same geometry and cache');
    }
    $basicSettings['seiger.settings.sGallery.format'] = 'webp';
    if ($canEncode('webp')) {
        $configured = (new sGalleryBuilder())->file('alpha.png')->getFile();
        $check($configured !== EVO_SITE_URL . 'alpha.png' && getimagesize($path($configured))[2] === IMAGETYPE_WEBP,
            'Configured format works without an explicit format() call');
    }
    $basicSettings['seiger.settings.sGallery.format'] = 'auto';
    if (!$withSpatie && $canEncode('webp')) {
        $basicSettings['seiger.settings.sGallery.optimize'] = true;
        $optimized = (new sGalleryBuilder())->file('alpha.png')->format('webp')->getFile();
        $check($optimized !== EVO_SITE_URL . 'alpha.png' && getimagesize($path($optimized))[2] === IMAGETYPE_WEBP,
            'Format-only processing also works with configured optimization and implicit WebP quality');
        $basicSettings['seiger.settings.sGallery.optimize'] = false;
    }
    file_put_contents($root . 'icon.svg', '<svg/>');
    $check((new sGalleryBuilder())->file('icon.svg')->resize(12)->format('png')->getFile() === EVO_SITE_URL . 'icon.svg', 'SVG stays original');
    $check((new sGalleryBuilder())->file('https://remote.test/image.png')->format('webp')->getFile() === 'https://remote.test/image.png', 'Remote URLs are returned without a download');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
