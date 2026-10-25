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
$loader = require getenv('SGALLERY_AUTOLOAD');
$loader->setPsr4('Seiger\\sGallery\\', dirname(__DIR__) . '/src');
$loader->addClassMap([
    \Seiger\sGallery\Builders\sGalleryBuilder::class => dirname(__DIR__) . '/src/Builders/sGalleryBuilder.php',
    \Seiger\sGallery\Services\GalleryCache::class => dirname(__DIR__) . '/src/Services/GalleryCache.php',
]);
$container = new \Illuminate\Container\Container();
\Illuminate\Support\Facades\Facade::setFacadeApplication($container);
$container->instance('log', new class {public function warning($m) {} public function error($m) {throw new RuntimeException($m);} });
$check = static function ($ok, $message) { if (!$ok) throw new RuntimeException($message); };
try {
    $source = imagecreatetruecolor(100, 100); imagefill($source, 0, 0, imagecolorallocate($source, 0, 0, 255)); imagepng($source, $root . '/assets/source.png'); if (PHP_VERSION_ID < 80500) imagedestroy($source);
    $mark = imagecreatetruecolor(20, 20); imagefill($mark, 0, 0, imagecolorallocate($mark, 255, 0, 0)); imagepng($mark, $root . '/assets/mark.png'); if (PHP_VERSION_ID < 80500) imagedestroy($mark);
    $original = hash_file('sha256', $root . '/assets/source.png');
    $build = static fn() => (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/source.png')->fit('crop', 80, 80)->format('png')->optimize(false);
    $url = $build()->watermark()->getFile();
    $path = $root . '/' . substr($url, strlen(EVO_SITE_URL));
    $check(is_file($path), 'Watermarked derivative exists');
    $result = imagecreatefrompng($path);
    $check(imagesx($result) === 80 && imagesy($result) === 80, 'Resize before watermark');
    // Imagick may emit an indexed PNG; resolve both palette indexes and true-color values.
    $pixel = imagecolorsforindex($result, imagecolorat($result, 70, 70)); if (PHP_VERSION_ID < 80500) imagedestroy($result);
    $check($pixel['red'] > 200 && $pixel['blue'] < 50, 'Watermark pixels appear in bottom-right');
    $check($build()->watermark([])->getFile() === $url, 'Options-only call uses default watermark');
    $check($build()->watermark('assets/mark.png', [])->getFile() === $url, 'Explicit watermark call matches default');
    // Fractional percentage results previously raised PHP float-to-int deprecations in Spatie.
    $fractional = imagecreatetruecolor(306, 270);
    imagefill($fractional, 0, 0, imagecolorallocate($fractional, 0, 0, 255));
    imagepng($fractional, $root . '/assets/fractional.png'); if (PHP_VERSION_ID < 80500) imagedestroy($fractional);
    $fractionalImage = \Spatie\Image\Image::load($root . '/assets/fractional.png');
    set_error_handler(static function ($severity, $message, $file, $line) {
        if ($severity === E_DEPRECATED) throw new ErrorException($message, 0, $severity, $file, $line);
        return false;
    });
    try {
        \Seiger\sGallery\Services\Watermark::apply($fractionalImage, [
            'file' => 'assets/mark.png', 'hash' => hash_file('sha256', $root . '/assets/mark.png'),
            'options' => \Seiger\sGallery\Services\Watermark::options([]),
        ]);
        $fractionalImage->save($root . '/fractional-result.png');
    } finally { restore_error_handler(); }
    $fractional = imagecreatefrompng($root . '/fractional-result.png');
    $pixel = imagecolorsforindex($fractional, imagecolorat($fractional, 296, 261));
    $outside = imagecolorsforindex($fractional, imagecolorat($fractional, 297, 262));
    $check($pixel['red'] > 200 && $outside['blue'] > 200, 'Percentage watermark uses truncated width and separate horizontal/vertical padding without deprecations');
    if (PHP_VERSION_ID < 80500) imagedestroy($fractional);
    $before = filemtime($path); $check($build()->watermark()->getFile() === $url && filemtime($path) === $before, 'Repeated call reuses cache');
    $check($build()->watermark(['position' => 'top-left'])->getFile() !== $url, 'Options affect cache');
    $mark = imagecreatetruecolor(20, 20); imagefill($mark, 0, 0, imagecolorallocate($mark, 0, 255, 0)); imagepng($mark, $root . '/assets/mark.png'); if (PHP_VERSION_ID < 80500) imagedestroy($mark);
    $check($build()->watermark()->getFile() !== $url, 'Replacement watermark under same name invalidates cache');
    $check(hash_file('sha256', $root . '/assets/source.png') === $original, 'Original unchanged');
    $reusable = $build()->format('original')->watermark('assets/mark.png');
    $reusableUrl = $reusable->getFile();
    $check($reusable->getFile() === $reusableUrl, 'Reusing Builder does not watermark a derivative again');
    $descriptor = new ReflectionMethod($reusable, 'optimizationDescriptor');
    $job = $descriptor->invoke($reusable, 'png');
    $check((new \Seiger\sGallery\Builders\sGalleryBuilder())->processOptimization($job) === $reusableUrl, 'Background replay reuses the same cache identity');
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
    imagepng($alpha, $root . '/assets/source.png'); if (PHP_VERSION_ID < 80500) imagedestroy($alpha);
    $url = $build()->format('original')->watermark('assets/mark.png')->getFile();
    $check(getimagesize($root . '/' . substr($url, strlen(EVO_SITE_URL)))['mime'] === 'image/png', 'Original format preserves transparent source as PNG');
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
