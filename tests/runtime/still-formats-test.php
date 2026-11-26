<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\ImageAnimation;
use Seiger\sGallery\Services\NativeImage;

function config($key, $default = null) { return $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }

require __DIR__ . '/bootstrap.php';

$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Surface unexpected rendering failures in the fixture. @since 1.9.0 */
    public function error(string $message): void { throw new RuntimeException($message); }

    /** Surface a legacy retry message for comparison. @since 1.9.0 */
    public function warning(string $message): void { echo "$message\n"; }
});

$avif = NativeImage::canEncode('avif', 'gd') || NativeImage::canEncode('avif', 'imagick');
$webp = NativeImage::canEncode('webp', 'gd') || NativeImage::canEncode('webp', 'imagick');
$gif = NativeImage::canEncode('gif', 'gd') || NativeImage::canEncode('gif', 'imagick');
printf("PHP %s, GD %s, Imagick %s, AVIF %s, GIF %s\n", PHP_VERSION,
    extension_loaded('gd') ? 'on' : 'off', extension_loaded('imagick') ? 'on' : 'off',
    $avif ? 'on' : 'off', $gif ? 'on' : 'off');

$root = sys_get_temp_dir() . '/sgallery-still-formats-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');

$check = static function (bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo "PASS $label\n";
};

try {
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    // Four bands have opacities of 0%, 25%, 75% and 100%.
    $row = "\0";
    foreach ([[220, 12, 20, 0], [235, 190, 30, 64], [25, 40, 230, 192], [25, 220, 42, 255]] as $pixel) {
        $row .= str_repeat(pack('C*', ...$pixel), 24);
    }
    $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 96, 48, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress(str_repeat($row, 48))) . $chunk('IEND', '');
    file_put_contents($root . 'source.png', $png);
    $builder = static fn (string $format): sGalleryBuilder =>
        (new sGalleryBuilder())->file('source.png')->format($format)->quality(90)->optimize(false);
    $path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $samples = static function (string $file, string $format, array $points, int $y): array {
        $loader = 'imagecreatefrom' . $format;
        if (function_exists($loader) && ($frame = @$loader($file)) instanceof GdImage) {
            return array_map(static function (int $x) use ($frame, $y): array {
                $pixel = imagecolorsforindex($frame, imagecolorat($frame, $x, $y));
                return [$pixel['red'], $pixel['green'], $pixel['blue'], 1 - $pixel['alpha'] / 127];
            }, $points);
        }
        $frame = new Imagick($file);
        return array_map(static function (int $x) use ($frame, $y): array {
            $pixel = $frame->getImagePixelColor($x, $y);
            $c = $pixel->getColor();
            return [$c['r'], $c['g'], $c['b'], $pixel->getColorValue(Imagick::COLOR_ALPHA)];
        }, $points);
    };
    $size = static fn (string $file): array => array_slice(getimagesize($file), 0, 3);

    if ($gif) {
        $url = $builder('gif')->fit('stretch', 48, 24)->getFile();
        $file = $path($url);
        $check($size($file) === [48, 24, IMAGETYPE_GIF] && !ImageAnimation::isAnimated($file, IMAGETYPE_GIF), 'GIF output is a decoded still frame');
        $pixels = $samples($file, 'gif', [6, 18, 30, 42], 12);
        $check(array_map('floatval', array_column($pixels, 3)) === [0.0, 0.0, 1.0, 1.0], 'GIF quantizes alpha to a transparent index');
        $check($pixels[2][2] > 190 && $pixels[3][1] > 190, 'GIF keeps the colors of opaque pixels');
        touch($file, 1700000000);
        clearstatcache(true, $file);
        $check($builder('gif')->fit('stretch', 48, 24)->getFile() === $url && filemtime($file) === 1700000000, 'A valid cached GIF is reused');
        // Supply valid PNG bytes under the old GIF name.
        file_put_contents($file, $png);
        $check($builder('gif')->fit('stretch', 48, 24)->getFile() === $url && $size($file) === [48, 24, IMAGETYPE_GIF],
            'A different format in the GIF cache is regenerated');
        $check($size($path($builder('gif')->getFile())) === [96, 48, IMAGETYPE_GIF], 'GIF conversion works at the source dimensions');
    } else {
        $check($builder('gif')->getFile() === EVO_SITE_URL . 'source.png', 'Native GIF serves the original when no encoder is available');
    }

    if ($avif || $webp) {
        $render = static fn (): string => $builder('avif')->resize(36, 18)->getFile();
        $url = $render();
        $file = $path($url);
        $format = $avif ? 'avif' : 'webp';
        $type = $avif ? IMAGETYPE_AVIF : IMAGETYPE_WEBP;
        $check(str_ends_with($url, ".$format") && $size($file) === [36, 18, $type], "AVIF request produces valid $format bytes");
        $pixels = $samples($file, $format, [4, 13, 22, 31], 9);
        $alpha = array_column($pixels, 3);
        $check($alpha[0] < 0.03 && abs($alpha[1] - 0.25) < 0.06 && abs($alpha[2] - 0.75) < 0.06 && $alpha[3] > 0.97,
            "Native $format keeps full and partial alpha");
        file_put_contents($file, $png);
        $check($render() === $url && $size($file) === [36, 18, $type], "A falsely labelled $format cache is regenerated");
        touch($file, 1700000000);
        clearstatcache(true, $file);
        $check($render() === $url && filemtime($file) === 1700000000, "A valid cached $format result is reused");
        $check($size($path($builder('avif')->getFile())) === [96, 48, $type], 'Format-only AVIF request keeps the original dimensions');
    } else {
        $check($builder('avif')->getFile() === EVO_SITE_URL . 'source.png', 'AVIF serves the original without AVIF or WebP support');
    }
    $frame = static fn (int $sequence): string => pack('NNNNNnnCC', $sequence, 96, 48, 0, 0, 1, 10, 0, 0);
    $data = gzcompress(str_repeat($row, 48));
    $apng = substr($png, 0, 33) . $chunk('acTL', pack('NN', 2, 0))
        . $chunk('fcTL', $frame(0)) . $chunk('IDAT', $data)
        . $chunk('fcTL', $frame(1)) . $chunk('fdAT', pack('N', 2) . $data) . $chunk('IEND', '');
    file_put_contents($root . 'animated.png', $apng);
    foreach (['avif', 'gif'] as $format) {
        $check((new sGalleryBuilder())->file('animated.png')->format($format)->getFile() === EVO_SITE_URL . 'animated.png',
            "A native still $format request keeps the animated PNG source");
    }
    $check(file_get_contents($root . 'source.png') === $png, 'The source PNG remains intact');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
