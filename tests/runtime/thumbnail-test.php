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
     * Exercise AVIF cache boundaries independently of the local encoder build.
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
$im = imagecreatetruecolor(128, 128);
for ($x = 0; $x < 128; $x++) {
    for ($y = 0; $y < 128; $y++) {
        imagesetpixel($im, $x, $y, imagecolorallocate($im, ($x * 7 + $y * 3) % 256, ($x * 13 + $y * 5) % 256, ($x * 3 + $y * 11) % 256));
    }
}
imagejpeg($im, $root . 'media/photo.jpg');
unset($im);
file_put_contents($root . 'media/icon.svg', '<svg/>');
use Seiger\sGallery\Services\GalleryCache as G;
/**
 * Support isolated package assertions without a site bootstrap.
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
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.8.0
 */
function seedReady($files)
{
    global $root;
    foreach (glob($root . 'assets/cache/m/photo-*') as $p) {
        unlink($p);
    }
    foreach ($files as $ext => $size) {
        file_put_contents($root . 'assets/cache/m/photo-max-768x504-q85.' . $ext, str_repeat('x', $size));
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
$cases = [['avif', ['avif' => 150], 'ready AVIF'], ['avif', ['avif' => 100], 'AVIF 100-byte boundary'], ['avif', ['avif' => 99, 'webp' => 150], 'small AVIF uses WebP'], ['avif', ['webp' => 150], 'missing AVIF uses WebP'], ['avif', ['avif' => 0, 'webp' => 150], 'empty AVIF uses WebP'], ['avif', ['avif' => 99, 'webp' => 100], 'small AVIF/WebP boundary'], ['avif', ['avif' => 99], 'small AVIF generates WebP fallback'], ['webp', ['webp' => 150], 'ready WebP'], ['original', ['jpg' => 150], 'ready original JPEG'], ['webp', ['webp' => 0], 'empty WebP regenerated'], ['webp', [], 'missing WebP generated']];
foreach ($cases as [$format, $files, $label]) {
    seedReady($files);
    $b = runReady(NewReady::class, $format);
    $ext = $format === 'original' ? 'jpg' : ($format === 'avif' && (($files['avif'] ?? 0) >= 100 || ($files['avif'] ?? 0) > 0 && ($files['webp'] ?? 0) === 100) ? 'avif' : 'webp');
    ckReady($b === EVO_SITE_URL . 'assets/cache/m/photo-max-768x504-q85.' . $ext, 'expected URL: ' . $label);
    if (in_array($label, ['empty WebP regenerated', 'missing WebP generated', 'small AVIF generates WebP fallback'])) {
        $path = str_replace(EVO_SITE_URL, EVO_BASE_PATH, $b);
        ckReady(is_file($path) && filesize($path) > 100 && getimagesize($path) !== false, 'generated output validated: ' . $label);
    }
}
foreach (['media/icon.svg', 'absent.jpg'] as $f) {
    ckReady(runReady(NewReady::class, 'webp', $f) === ($f === 'media/icon.svg' ? EVO_SITE_URL . $f : Seiger\sGallery\Models\sGalleryModel::NOIMAGE), 'SVG/missing source unchanged');
}
file_put_contents($root . 'media/broken.jpg', 'not an image');
ckReady(runReady(NewReady::class, 'webp', 'media/broken.jpg') === Seiger\sGallery\Models\sGalleryModel::NOIMAGE, 'corrupt source does not produce an unchecked output');
seedReady(['webp' => 150]);
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
