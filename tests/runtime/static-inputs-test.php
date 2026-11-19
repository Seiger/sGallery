<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\Services\ImageColor;
use Seiger\sGallery\Services\NativeImage;
use Spatie\Image\Image;

function config($key, $default = null) { return $key === 'seiger.settings.sGallery.optimize' ? false : $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }
function evo() { return new class {
    /** Isolate optional queue discovery from a real CMS. @since 1.9.0 */
    public function getConfig($key, $default = null) { return $default; }
}; }
require __DIR__ . '/bootstrap.php';
$withSpatie = class_exists(Image::class);
printf("PHP %s, Spatie %s, GD %s, Imagick %s\n", PHP_VERSION, $withSpatie ? 'on' : 'off', extension_loaded('gd') ? 'on' : 'off', class_exists(Imagick::class) ? 'on' : 'off');
$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Record expected codec failures without hiding their assertions. @since 1.9.0 */
    public function error(string $message): void { $GLOBALS['staticErrors'][] = $message; }
    /** Accept a missing optional queue or AVIF encoder. @since 1.9.0 */
    public function warning(string $message): void {}
});
$root = sys_get_temp_dir() . '/sgallery-static-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label . ' ' . json_encode($GLOBALS['staticErrors'] ?? []));
    echo "PASS $label\n";
};
$path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
$canWrite = static fn (string $format): bool => NativeImage::canEncode($format, 'gd') || NativeImage::canEncode($format, 'imagick');
$sample = static function (string $file, int $x, int $y): array {
    $frame = NativeImage::open($file, 'png')->frame();
    if ($frame instanceof Imagick) {
        $pixel = $frame->getImagePixelColor($x, $y);
        $rgb = $pixel->getColor();
        return [$rgb['r'], $rgb['g'], $rgb['b'], $pixel->getColorValue(Imagick::COLOR_ALPHA)];
    }
    $rgba = imagecolorsforindex($frame, imagecolorat($frame, $x, $y));
    return [$rgba['red'], $rgba['green'], $rgba['blue'], 1 - $rgba['alpha'] / 127];
};
try {
    foreach (glob(__DIR__ . '/../fixtures/static-inputs/*') as $file) {
        // Use distinct basenames: the existing cache key omits the source extension.
        $name = pathinfo($file, PATHINFO_FILENAME) . '-' . pathinfo($file, PATHINFO_EXTENSION) . '.' . pathinfo($file, PATHINFO_EXTENSION);
        copy($file, $root . $name);
    }
    foreach (['gif', 'webp', 'avif', 'bmp', 'tiff'] as $format) {
        $source = 'alpha-' . $format . '.' . $format;
        $original = EVO_SITE_URL . $source;
        $hash = hash_file('sha256', $root . $source);
        $readable = false;
        try { NativeImage::open($source, 'png'); $readable = true; } catch (RuntimeException) {}
        $cases = [
            'resize' => [static fn ($b) => $b->resize(16, 8)->format('png'), 16, 8, 'png'],
            'fit' => [static fn ($b) => $b->fit('max', 15, 15)->format('png'), 15, 8, 'png'],
            'crop' => [static fn ($b) => $b->crop('topLeft', 8, 8)->format('png'), 8, 8, 'png'],
            'manualCrop' => [static fn ($b) => $b->manualCrop(2, 2, 8, 6)->format('png'), 8, 6, 'png'],
            'focalCrop' => [static fn ($b) => $b->focalCrop(12, 8, 8, 6)->format('png'), 8, 6, 'png'],
            'format' => [static fn ($b) => $b->format('png'), 32, 16, 'png'],
            'jpeg' => [static fn ($b) => $b->resize(16, 8)->format('jpeg'), 16, 8, 'jpeg'],
        ];
        foreach ($cases as $name => [$operation, $width, $height, $output]) {
            $render = static fn (): string => $operation((new sGalleryBuilder())->file($source))->getFile();
            $url = $render();
            if (!$readable || !$canWrite($output)) {
                $check($url === ($withSpatie ? sGalleryModel::NOIMAGE : $original), "$format $name missing codec fallback ($url)");
                continue;
            }
            $info = $url !== $original ? @getimagesize($path($url)) : false;
            $check($info !== false && array_slice($info, 0, 2) === [$width, $height] && $info['mime'] === 'image/' . $output,
                "$format $name renders $width x $height $output ($url)");
            touch($path($url), 1700000000);
            $check($render() === $url && filemtime($path($url)) === 1700000000, "$format $name reuses verified cache");
            if (!$withSpatie && $format !== 'bmp' && $name === 'resize') {
                $pixel = $sample(substr($url, strlen(EVO_SITE_URL)), 12, 6);
                $check($pixel[3] < 0.08, "$format PNG derivative retains its transparent quadrant");
            }
            if (!$withSpatie && $format !== 'bmp' && $name === 'jpeg') {
                $pixel = $sample(substr($url, strlen(EVO_SITE_URL)), 12, 6);
                $check(min(array_slice($pixel, 0, 3)) > 230, "$format JPEG composites alpha on white");
            }
        }
        // A worker must render a cold operation, then share its result with the frontend.
        if ($readable) {
            $image = (new sGalleryBuilder())->file($source)->fit('max', 11, 11)->format('png');
            $descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
            $url = (new sGalleryBuilder())->processOptimization($descriptor->invoke($image, 'png'));
            $check(array_slice(getimagesize($path($url)), 0, 3) === [11, 6, IMAGETYPE_PNG] && $image->getFile() === $url,
                "$format cold worker shares the frontend result");
        }
        $check(hash_file('sha256', $root . $source) === $hash, "$format source bytes preserved");
        if (!$withSpatie) {
            $check((new sGalleryBuilder())->file($source)->getFile() === $original, "$format no-operation retains original");
            $check((new sGalleryBuilder())->file($source)->format('original')->getFile() === $original, "$format original-only retains original");
        }
    }
    if (!$withSpatie) {
        $bmp = (new sGalleryBuilder())->file('opaque-bmp.bmp')->fit('max', 16)->format('png')->getFile();
        $check($canWrite('png') ? $bmp !== EVO_SITE_URL . 'opaque-bmp.bmp' && array_slice(getimagesize($path($bmp)), 0, 3) === [16, 8, IMAGETYPE_PNG]
            : $bmp === EVO_SITE_URL . 'opaque-bmp.bmp', '24-bit BMP renders with GD or Imagick');
        file_put_contents($root . 'big-endian.tiff', base64_decode(
            'TU0AKgAAAAgACQEAAAQAAAABAAAABAEBAAQAAAABAAAAAgECAAMAAAABAAgAAAEDAAMAAA'
            . 'ABAAEAAAEGAAMAAAABAAEAAAERAAQAAAABAAAAegEVAAMAAAABAAEAAAEWAAQAAAABAAAAAgEXAAQAAAABAAAACAAAAAAAVar/AFWq/w==', true));
        $tiff = (new sGalleryBuilder())->file('big-endian.tiff')->fit('max', 2)->format('png')->getFile();
        $check(class_exists(Imagick::class) ? $tiff !== EVO_SITE_URL . 'big-endian.tiff' && array_slice(getimagesize($path($tiff)), 0, 3) === [2, 1, IMAGETYPE_PNG]
            : $tiff === EVO_SITE_URL . 'big-endian.tiff', 'Big-endian single-page TIFF follows the Imagick reader');
        $check((new sGalleryBuilder())->file('pages-tiff.tiff')->fit('max', 16)->format('png')->getFile() === EVO_SITE_URL . 'pages-tiff.tiff',
            'Multi-page TIFF remains original instead of dropping pages');
        $url = (new sGalleryBuilder())->file('oriented-tiff.tiff')->fit('max', 16)->format('png')->getFile();
        $check(class_exists(Imagick::class)
            ? $url !== EVO_SITE_URL . 'oriented-tiff.tiff' && array_slice(getimagesize($path($url)), 0, 3) === [8, 16, IMAGETYPE_PNG]
            : $url === EVO_SITE_URL . 'oriented-tiff.tiff', 'TIFF orientation follows its supported metadata reader');
        foreach (['webp', 'avif', 'tiff', 'gif', 'bmp'] as $format) {
            $source = 'p3-' . $format . '.' . $format;
            $url = (new sGalleryBuilder())->file($source)->fit('max', 16)->format('png')->getFile();
            if (!class_exists(Imagick::class)) {
                $check($url === EVO_SITE_URL . $source, "$format ICC retains original without a color driver");
                continue;
            }
            $frame = new Imagick($path($url));
            $check($url !== EVO_SITE_URL . $source && $frame->getImageProfile('icc') === ImageColor::srgbProfile(),
                "$format ICC is converted and tagged sRGB");
            $reference = new Imagick($root . $source);
            $reference->profileImage('icc', ImageColor::srgbProfile());
            $expected = $reference->getImagePixelColor(4, 4)->getColor();
            $actual = $frame->getImagePixelColor(4, 4)->getColor();
            $check(max(abs($expected['r'] - $actual['r']), abs($expected['g'] - $actual['g']), abs($expected['b'] - $actual['b'])) < 4,
                "$format ICC derivative keeps the converted color");
        }
        copy($root . 'alpha-webp.webp', $root . 'misnamed.jpg');
        $url = (new sGalleryBuilder())->file('misnamed.jpg')->fit('max', 16)->format('png')->getFile();
        $readable = false;
        try { NativeImage::open('misnamed.jpg', 'png'); $readable = true; } catch (RuntimeException) {}
        $check($readable ? $url !== EVO_SITE_URL . 'misnamed.jpg' && getimagesize($path($url))[2] === IMAGETYPE_PNG : $url === EVO_SITE_URL . 'misnamed.jpg',
            'Input selection uses contents rather than the extension');
        $hdr = file_get_contents($root . 'alpha-avif.avif');
        $position = strpos($hdr, 'nclx');
        $check($position !== false, 'The AVIF fixture includes explicit container color metadata');
        file_put_contents($root . 'hdr.avif', substr_replace($hdr, pack('nnnC', 9, 16, 9, 128), $position + 4, 7));
        $check((new sGalleryBuilder())->file('hdr.avif')->fit('max', 16)->format('png')->getFile() === EVO_SITE_URL . 'hdr.avif',
            'Unsupported HDR AVIF stays original instead of losing its color description');
        $box = static fn (string $type, string $data): string => pack('N', strlen($data) + 8) . $type . $data;
        foreach ([
            'hdr' => $box('colr', 'nclx' . pack('nnnC', 9, 16, 9, 128)),
            'rotation' => $box('irot', "\x01"),
        ] as $name => $property) {
            file_put_contents($root . "$name.avif", $box('meta', "\0\0\0\0" . $box('iprp', $box('ipco', $property))));
            $check(ImageColor::fromRaster($root . "$name.avif", IMAGETYPE_AVIF)[1] === ImageColor::UNSUPPORTED,
                "GD preserves AVIF with unsupported $name metadata");
        }
        file_put_contents($root . 'sequence.avif', $box('ftyp', "avis\0\0\0\0avisavif"));
        $check(ImageColor::fromRaster($root . 'sequence.avif', IMAGETYPE_AVIF)[1] === ImageColor::UNSUPPORTED,
            'GD rejects AVIF sequences instead of flattening them');
        file_put_contents($root . 'broken.webp', 'RIFF' . pack('V', 9999) . 'WEBP');
        $check(ImageColor::fromRaster($root . 'broken.webp', IMAGETYPE_WEBP)[1] === ImageColor::UNSUPPORTED,
            'Truncated WebP metadata is rejected');
        $pending = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_contains($file->getFilename(), '.pending-')) $pending[] = $file->getPathname();
        }
        $check($pending === [], 'No incomplete output is published');
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
