<?php
/** Standalone real-GD regression coverage for watermark cache and source integrity. @since 1.7.0 */
$root = sys_get_temp_dir() . '/sgallery-watermark-' . bin2hex(random_bytes(6));
mkdir($root . '/assets', 0755, true);
define('EVO_BASE_PATH', $root . '/');
define('EVO_SITE_URL', 'https://example.test/');
$settings = ['imageCacheDir' => 'cache/', 'optimize' => false, 'watermark' => ['file' => 'assets/mark.png']];
function config($key, $default = null) { return \Illuminate\Support\Arr::get($GLOBALS['settings'], substr($key, strlen('seiger.settings.sGallery.')), $default); }
function storage_path($path) {return $GLOBALS['root'] . '/storage/' . $path;}
function evo() { return new class { public function getConfig($key, $default = null) {return $default;} public function make($key) {throw new RuntimeException('No optional worker');} }; }
if (!getenv('PACKAGE_AUTOLOAD') && getenv('SGALLERY_AUTOLOAD')) {
    putenv('PACKAGE_AUTOLOAD=' . getenv('SGALLERY_AUTOLOAD'));
}
require __DIR__ . '/runtime/bootstrap.php';
printf("PHP %s, Imagick %s\n", PHP_VERSION, extension_loaded('imagick') ? 'on' : 'off');
$container = new \Illuminate\Container\Container();
\Illuminate\Support\Facades\Facade::setFacadeApplication($container);
$container->instance('log', new class {public function warning($m) {} public function error($m) {throw new RuntimeException($m);} });
$check = static function ($ok, $message) { if (!$ok) throw new RuntimeException($message); };
try {
    $source = imagecreatetruecolor(100, 100); imagefill($source, 0, 0, imagecolorallocate($source, 0, 0, 255)); imagepng($source, $root . '/assets/source.png'); unset($source);
    $mark = imagecreatetruecolor(20, 20); imagefill($mark, 0, 0, imagecolorallocate($mark, 255, 0, 0)); imagepng($mark, $root . '/assets/mark.png'); unset($mark);
    $original = hash_file('sha256', $root . '/assets/source.png');
    $build = static fn() => (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/source.png')->fit('crop', 80, 80)->format('png')->optimize(false);
    $url = $build()->watermark()->getFile();
    $path = $root . '/' . substr($url, strlen(EVO_SITE_URL));
    $check(is_file($path), 'Watermarked derivative exists');
    $result = imagecreatefrompng($path);
    $check(imagesx($result) === 80 && imagesy($result) === 80, 'Resize before watermark');
    // Imagick may emit an indexed PNG; resolve both palette indexes and true-color values.
    $pixel = imagecolorsforindex($result, imagecolorat($result, 70, 70)); unset($result);
    $check($pixel['red'] > 200 && $pixel['blue'] < 50, 'Watermark pixels appear in bottom-right');
    $check($build()->watermark([])->getFile() === $url, 'Options-only call uses default watermark');
    $check($build()->watermark('assets/mark.png', [])->getFile() === $url, 'Explicit watermark call matches default');
    // Fractional percentage sizes are resolved to integer pixels.
    $fractional = imagecreatetruecolor(306, 270);
    imagefill($fractional, 0, 0, imagecolorallocate($fractional, 0, 0, 255));
    imagepng($fractional, $root . '/assets/fractional.png'); unset($fractional);
    $fractionalUrl = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/fractional.png')->format('png')->watermark()->getFile();
    $fractionalPath = $root . '/' . substr($fractionalUrl, strlen(EVO_SITE_URL));
    $check(is_file($fractionalPath) && $fractionalUrl !== EVO_SITE_URL . 'assets/fractional.png', 'Watermark alone produces a derivative');
    $fractional = imagecreatefrompng($fractionalPath);
    $pixel = imagecolorsforindex($fractional, imagecolorat($fractional, 296, 261));
    $outside = imagecolorsforindex($fractional, imagecolorat($fractional, 297, 262));
    $check($pixel['red'] > 200 && $outside['blue'] > 200, 'Percentage watermark uses truncated width and separate horizontal/vertical padding without deprecations');
    unset($fractional);
    $before = 1700000000;
    touch($path, $before);
    clearstatcache(true, $path);
    $check($build()->watermark()->getFile() === $url && filemtime($path) === $before, 'Repeated call reuses cache');
    $check($build()->watermark(['position' => 'top-left'])->getFile() !== $url, 'Options affect cache');
    $mark = imagecreatetruecolor(20, 20); imagefill($mark, 0, 0, imagecolorallocate($mark, 0, 255, 0)); imagepng($mark, $root . '/assets/mark.png'); unset($mark);
    $check($build()->watermark()->getFile() !== $url, 'Replacement watermark under same name invalidates cache');
    $check(hash_file('sha256', $root . '/assets/source.png') === $original, 'Original unchanged');
    $reusable = $build()->format('original')->watermark('assets/mark.png');
    $reusableUrl = $reusable->getFile();
    $check($reusable->getFile() === $reusableUrl, 'Reusing Builder does not watermark a derivative again');
    $settings['watermark']['file'] = '';
    $check($build()->watermark()->getFile() === $build()->getFile(), 'Missing default is a no-op');
    try {$build()->watermark('assets/missing.png');throw new RuntimeException('Missing explicit watermark accepted');} catch (InvalidArgumentException $expected) {}
    foreach (['png','jpg','jpeg','gif','webp','avif','original'] as $format) {
        if ($format === 'avif' && !function_exists('imageavif')) continue;
        $url = $build()->format($format)->watermark('assets/mark.png')->getFile();
        $check(is_file($root . '/' . substr($url, strlen(EVO_SITE_URL))), 'Format ' . $format . ' generated');
        if ($format === 'original') $check(str_ends_with($url, '.png'), 'Original source extension preserved');
    }
    $alpha = imagecreatetruecolor(100, 100);
    imagealphablending($alpha, false); imagesavealpha($alpha, true);
    imagefill($alpha, 0, 0, imagecolorallocatealpha($alpha, 0, 0, 255, 80));
    imagepng($alpha, $root . '/assets/source.png'); unset($alpha);
    $url = $build()->format('original')->watermark('assets/mark.png')->getFile();
    $check(getimagesize($root . '/' . substr($url, strlen(EVO_SITE_URL)))['mime'] === 'image/png', 'Original format preserves transparent source as PNG');

    $mark = imagecreatetruecolor(24, 12);
    imagealphablending($mark, false);
    imagesavealpha($mark, true);
    foreach ([0, 63, 127] as $band => $transparency) {
        imagefilledrectangle($mark, 8 * $band, 0, 8 * $band + 7, 11, imagecolorallocatealpha($mark, 255, 0, 0, $transparency));
    }
    imagepng($mark, $root . '/assets/alpha-mark.png');
    unset($mark);
    $options = ['position' => 'centerCenter', 'width' => '24px', 'padding' => '5px', 'opacity' => 50];
    $url = $build()->watermark('assets/alpha-mark.png', $options)->getFile();
    $result = imagecreatefrompng($root . '/' . substr($url, strlen(EVO_SITE_URL)));
    $pixelAt = static fn (int $x, int $y): array => imagecolorsforindex($result, imagecolorat($result, $x, $y));
    $opaqueMark = $pixelAt(36, 42);
    $translucentMark = $pixelAt(45, 42);
    $transparentMark = $pixelAt(53, 42);
    $base = $pixelAt(32, 42);
    $check(abs($opaqueMark['alpha'] - 40) <= 2 && abs($translucentMark['alpha'] - 60) <= 2 &&
        $opaqueMark['red'] > $translucentMark['red'] && $transparentMark === $base && $base['alpha'] === 80,
        'Native opacity multiplies each mark pixel opacity and preserves the base alpha');
    $check($pixelAt(36, 38) === $base && $pixelAt(36, 50) === $opaqueMark && $pixelAt(36, 51) === $base,
        'Native center alias, pixel padding and watermark aspect ratio match the placement contract');
    $url = $build()->watermark('assets/alpha-mark.png', array_replace($options, ['opacity' => 0]))->getFile();
    $zero = imagecreatefrompng($root . '/' . substr($url, strlen(EVO_SITE_URL)));
    $check(imagecolorsforindex($zero, imagecolorat($zero, 36, 42)) === $base, 'Zero opacity leaves the pixels unchanged');
    unset($result, $pixelAt, $zero);

    if (\Seiger\sGallery\Services\NativeImage::canEncode('gif', 'imagick')) {
        $sequence = new Imagick();
        foreach (['blue', 'lime'] as $index => $color) {
            $frame = new Imagick();
            $frame->newImage(32, 24, $color, 'GIF');
            $frame->setImageDelay(12 + 8 * $index);
            $frame->setImageIterations(3);
            $sequence->addImage($frame);
        }
        $sequence->setFirstIterator();
        $sequence->writeImages($root . '/assets/source.gif', true);
        foreach (['gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP] as $format => $type) {
            if (!\Seiger\sGallery\Services\NativeImage::canEncode($format, 'imagick')) continue;
            $url = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/source.gif')->format($format)
                ->quality(90)->watermark('assets/alpha-mark.png', ['position' => 'top-left', 'width' => 12, 'padding' => 0])->getFile();
            $file = $root . '/' . substr($url, strlen(EVO_SITE_URL));
            $check($url !== EVO_SITE_URL . 'assets/source.gif' &&
                \Seiger\sGallery\Services\ImageAnimation::timing($file, $type) === ['loops' => 3, 'delays' => [120, 200]],
                "Native $format watermark preserves frame count, delays and looping");
            foreach ((new Imagick($file))->coalesceImages() as $frame) {
                $check($frame->getImagePixelColor(1, 1)->getColor()['r'] > 220, "Native $format watermark appears in each frame");
            }
        }
    }

    file_put_contents($root . '/cache/unrelated.txt', 'preserve');
    file_put_contents($root . '/cache/protected.png', 'source');
    file_put_contents($root . '/cache/generated.png', 'derived');
    \Seiger\sGallery\Services\GalleryCache::remember('assets/source.png', 'cache/protected.png');
    \Seiger\sGallery\Services\GalleryCache::remember('cache/protected.png', 'cache/generated.png');
    $removed = \Seiger\sGallery\Services\GalleryCache::clear();
    $check($removed > 0 && is_file($root . '/assets/source.png'), 'Cache clearing preserves original');
    $check(is_file($root . '/cache/unrelated.txt') && is_file($root . '/cache/protected.png') && !is_file($root . '/cache/generated.png'), 'Cache clears only owned derivatives and protects derivative sources');
    $compiler = new \Illuminate\View\Compilers\BladeCompiler(new \Illuminate\Filesystem\Filesystem(), $root . '/compiled');
    foreach (glob(dirname(__DIR__) . '/views/*.blade.php') as $file) {
        $compiled = $root . '/' . basename($file) . '.php';
        file_put_contents($compiled, $compiler->compileString(file_get_contents($file)));
        exec(PHP_BINARY . ' -l ' . escapeshellarg($compiled), $output, $status);
        $check($status === 0, 'Blade compiles: ' . basename($file));
    }
    echo "sGallery watermark/cache/formats/Blade OK\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
