<?php

/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.8.0
 */
function config($key, $default = null)
{
    return ['seiger.settings.sGallery.imageCacheDir' => 'assets/cache/', 'seiger.settings.sGallery.optimize' => false, 'seiger.settings.sGallery.quality' => 100, 'seiger.settings.sGallery.format' => 'auto'][$key] ?? $default;
}
/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.8.0
 */
function storage_path($path = '')
{
    return EVO_BASE_PATH . 'storage/' . $path;
}
/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.8.0
 */
function evo()
{
    return $GLOBALS['evo'];
}
require __DIR__ . '/bootstrap.php';
/**
 * Provide a controlled fixture for package behavior comparison.
 *
 * @since 1.8.0
 */
class NewReady extends Seiger\sGallery\Builders\sGalleryBuilder
{
    /**
     * Let the Spatie path check the AVIF cache regardless of the local AVIF encoder.
     *
     * @since 1.8.0
     */
    protected function isAvifSupported(): bool
    {
        self::$avifSupportDriver = 'gd';
        return true;
    }
}
$root = sys_get_temp_dir() . '/ewt-ready-image-' . getmypid() . '/';
mkdir($root . 'media', 0755, true);
mkdir($root . 'assets/cache/m', 0755, true);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$app = new Illuminate\Container\Container();
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$app->instance('log', new class
{
    /**
     * Accept expected fallback warnings without writing application logs.
     *
     * @since 1.8.0
     */
    function warning($x)
    {
    }
    /**
     * Record image fallback failures for isolated file-integrity assertions.
     *
     * @since 1.8.0
     */
    function error($x)
    {
        $GLOBALS['errors'][] = $x;
    }
});
$GLOBALS['evo'] = new class
{
    /**
     * Expose only the controlled configuration used by this isolated regression.
     *
     * @since 1.8.0
     */
    function getConfig($key, $default = null)
    {
        return $default;
    }
};
$withSpatie = class_exists(Spatie\Image\Image::class);
$cachePrefix = 'assets/cache/m/photo-max-768x504-q85' . ($withSpatie ? '' : '-native');
$cacheTimestamp = 1700000000;
printf("PHP %s, Spatie %s\n", PHP_VERSION, $withSpatie ? 'on' : 'off');

if (function_exists('imagecreatetruecolor')) {
    $im = imagecreatetruecolor(128, 128);
    for ($x = 0; $x < 128; $x++) {
        for ($y = 0; $y < 128; $y++) {
            imagesetpixel($im, $x, $y, imagecolorallocate($im, ($x * 7 + $y * 3) % 256, ($x * 13 + $y * 5) % 256, ($x * 3 + $y * 11) % 256));
        }
    }
    imagejpeg($im, $root . 'media/photo.jpg');
    ob_start();
    imagewebp($im, null, 85);
    $webp = ob_get_clean();
} else {
    $im = new Imagick();
    $im->newPseudoImage(128, 128, 'gradient:blue-red');
    $im->setImageFormat('JPEG');
    $im->writeImage($root . 'media/photo.jpg');
    $im->setImageFormat('WEBP');
    $im->setImageCompressionQuality(85);
    $webp = $im->getImageBlob();
}
$jpeg = file_get_contents($root . 'media/photo.jpg');
unset($im);
file_put_contents($root . 'media/icon.svg', '<svg/>');
use Seiger\sGallery\Services\GalleryCache as G;
/**
 * Stop at the first failing thumbnail assertion and identify the case.
 *
 * @since 1.8.0
 */
function ckReady($ok, $label)
{
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo "PASS {$label}\n";
}
/**
 * Seed derivatives and give them a known timestamp so tests can detect a rewrite.
 *
 * @param array<string, string> $files Encoded bytes or an empty file by extension.
 *
 * @since 1.8.0
 */
function seedReady($files)
{
    global $root, $cachePrefix, $cacheTimestamp;
    foreach (glob($root . 'assets/cache/m/photo-*') as $p) {
        unlink($p);
    }
    foreach ($files as $ext => $bytes) {
        $path = $root . $cachePrefix . '.' . $ext;
        file_put_contents($path, $bytes);
        touch($path, $cacheTimestamp);
    }
    clearstatcache();
}
/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.8.0
 */
function runReady($class, $format, $file = 'media/photo.jpg')
{
    return (string) (new $class())->file($file)->fit('max', 768, 504)->quality(85)->format($format);
}
$cases = [
    ['avif', ['avif' => $jpeg, 'webp' => $webp], 'webp', 'mislabeled AVIF uses cached WebP'],
    ['avif', ['webp' => $webp], 'webp', 'missing AVIF uses cached WebP'],
    ['avif', ['avif' => '', 'webp' => $webp], 'webp', 'empty AVIF uses cached WebP'],
    ['webp', ['webp' => $webp], 'webp', 'ready WebP'],
    ['original', ['jpg' => $jpeg], 'jpg', 'ready original JPEG'],
    ['webp', ['webp' => ''], 'webp', 'empty WebP regenerated'],
    ['webp', [], 'webp', 'missing WebP generated'],
];
foreach ($cases as [$format, $files, $ext, $label]) {
    seedReady($files);
    $b = runReady(NewReady::class, $format);
    $output = $cachePrefix . '.' . $ext;
    ckReady($b === EVO_SITE_URL . $output, 'expected URL: ' . $label);
    $path = $root . $output;
    if (($files[$ext] ?? '') !== '') {
        ckReady(filemtime($path) === $cacheTimestamp && file_get_contents($path) === $files[$ext], 'reused without rewriting: ' . $label);
    } else {
        ckReady(is_file($path) && (getimagesize($path)[2] ?? null) === IMAGETYPE_WEBP, 'generated output validated: ' . $label);
    }
}
if (!$withSpatie) {
    seedReady(['avif' => $jpeg, 'webp' => $jpeg]);
    $url = runReady(NewReady::class, 'avif');
    $ext = str_ends_with($url, '.avif') ? 'avif' : 'webp';
    $output = $cachePrefix . '.' . $ext;
    $type = $ext === 'avif' ? IMAGETYPE_AVIF : IMAGETYPE_WEBP;
    ckReady($url === EVO_SITE_URL . $output && (@getimagesize($root . $output)[2] ?? null) === $type,
        'native AVIF request rejects JPEG bytes cached as WebP');
}
foreach (['media/icon.svg', 'absent.jpg'] as $f) {
    ckReady(runReady(NewReady::class, 'webp', $f) === ($f === 'media/icon.svg' ? EVO_SITE_URL . $f : Seiger\sGallery\Models\sGalleryModel::NOIMAGE), 'SVG/missing source unchanged');
}
file_put_contents($root . 'media/broken.jpg', 'not an image');
$broken = $withSpatie ? Seiger\sGallery\Models\sGalleryModel::NOIMAGE : EVO_SITE_URL . 'media/broken.jpg';
ckReady(runReady(NewReady::class, 'webp', 'media/broken.jpg') === $broken, 'corrupt source does not produce an unchecked derivative');
seedReady(['webp' => $webp]);
runReady(NewReady::class, 'webp');
// Removing the index behind the memo must recover on the next registration.
$index = $root . 'assets/cache/.sgallery-index/';
foreach (glob($index . '*.json') as $f) {
    unlink($f);
}
rmdir($index);
runReady(NewReady::class, 'webp');
ckReady(count(glob($index . '*.json')) === 1, 'externally removed index repaired');
$count = G::clear();
ckReady($count > 0 && is_file($root . 'media/photo.jpg'), 'owned derivatives cleared, original retained');
echo "ALL READY THUMBNAIL CHECKS PASSED\n";
