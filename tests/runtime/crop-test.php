<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\NativeImage;

function config($key, $default = null) { return $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }

require __DIR__ . '/bootstrap.php';

$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Surface an unexpected generation failure. @since 1.9.0 */
    public function error(string $message): void
    {
        throw new RuntimeException($message);
    }
});

$gd = NativeImage::canEncode('png', 'gd');
$imagick = NativeImage::canEncode('png', 'imagick');
printf("PHP %s, GD %s, Imagick %s\n", PHP_VERSION, $gd ? 'on' : 'off', $imagick ? 'on' : 'off');

$root = sys_get_temp_dir() . '/sgallery-crop-' . bin2hex(random_bytes(6)) . '/';
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
    // Encode X and Y in the red and green channels so that every origin is testable.
    $pixels = '';
    for ($y = 0; $y < 137; ++$y) {
        $pixels .= "\x00";
        for ($x = 0; $x < 201; ++$x) {
            $pixels .= pack('CCCC', $x, $y, 127, $x === 130 && $y === 84 ? 128 : 255);
        }
    }
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 201, 137, 8, 6, 0, 0, 0)) .
        $chunk('IDAT', gzcompress($pixels)) . $chunk('IEND', '');
    file_put_contents($root . 'source.png', $png);
    $builder = static fn (): sGalleryBuilder => (new sGalleryBuilder())->file('source.png')->format('png')->optimize(false);

    if (!$gd && !$imagick) {
        $check($builder()->crop('left', 50)->getFile() === EVO_SITE_URL . 'source.png', 'Crop serves the original without a codec');
        return;
    }

    $pixel = static function (string $path, int $x, int $y) use ($gd): array {
        if ($gd) {
            $frame = imagecreatefrompng($path);
            $color = imagecolorsforindex($frame, imagecolorat($frame, $x, $y));
            return [$color['red'], $color['green'], 1 - $color['alpha'] / 127];
        }
        $pixel = (new Imagick($path))->getImagePixelColor($x, $y);
        $color = $pixel->getColor();
        return [$color['r'], $color['g'], $pixel->getColorValue(Imagick::COLOR_ALPHA)];
    };
    $verify = static function (string $url, int $x, int $y, int $w, int $h, string $label) use ($root, $pixel, $check): string {
        $path = $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
        $info = is_file($path) ? getimagesize($path) : false;
        $check($info !== false && array_slice($info, 0, 3) === [$w, $h, IMAGETYPE_PNG] && $url !== EVO_SITE_URL . 'source.png', "$label dimensions");
        $check(array_slice($pixel($path, 0, 0), 0, 2) === [$x, $y], "$label starts at ($x, $y)");
        $check(str_ends_with(pathinfo($path, PATHINFO_FILENAME), '-native'), "$label cache namespace");
        return $path;
    };

    foreach ([
        'topLeft' => [0, 0], 'top' => [75, 0], 'topRight' => [151, 0],
        'left' => [0, 45], 'center' => [75, 45], 'right' => [151, 45],
        'bottomLeft' => [0, 91], 'bottom' => [75, 91], 'bottomRight' => [151, 91],
    ] as $position => [$x, $y]) {
        $verify($builder()->crop($position, 50, 46)->getFile(), $x, $y, 50, 46, $position);
    }
    $check($builder()->crop('unknown', 50, 46)->getFile() === $builder()->crop('center', 50, 46)->getFile(), 'Unknown crop position uses center');
    $verify($builder()->crop('bottomRight', 250, 9)->getFile(), 0, 128, 201, 9, 'Positional crop wider than the image');
    $verify($builder()->manualCrop(23, 42, 17)->getFile(), 23, 42, 17, 17, 'Manual square default');
    $verify($builder()->manualCrop(197, 133, 12, 10)->getFile(), 197, 133, 4, 4, 'Manual crop intersecting right and bottom edges');
    $focal = $verify($builder()->focalCrop(150, 100, 41, 33)->getFile(), 129, 83, 41, 33, 'Focal crop uses source pixels');
    $check(abs($pixel($focal, 1, 1)[2] - 0.5) < 0.02, 'Crop preserves partial transparency');
    $verify($builder()->focalCrop(195, 130, 45, 29)->getFile(), 156, 108, 45, 29, 'Focal crop shifts inside the bottom-right edge');
    $verify($builder()->focalCrop(-1, -4, 11, 9)->getFile(), 0, 0, 11, 9, 'Focal crop clamps a point outside the top-left edge');
    $verify($builder()->manualCrop(10, 12, 20)->crop('topRight', 24)->getFile(), 177, 0, 24, 24, 'Positional crop takes precedence over manual crop');
    $fitUrl = $builder()->crop('topRight', 24)->fit('max', 25)->getFile();
    $fitPath = $root . rawurldecode(substr($fitUrl, strlen(EVO_SITE_URL)));
    $check(array_slice(getimagesize($fitPath), 0, 3) === [25, 17, IMAGETYPE_PNG], 'Fit takes precedence over positional crop');

    $manual = static fn (int $x, int $y): string => $builder()->manualCrop($x, $y, 11, 9)->getFile();
    $a = $manual(5, 7);
    $b = $manual(11, 13);
    $check($a !== $b, 'Equal-sized manual crops use different coordinate-based cache keys');
    $path = $verify($a, 5, 7, 11, 9, 'First cached manual crop');
    $verify($b, 11, 13, 11, 9, 'Second cached manual crop');
    touch($path, 1700000000);
    clearstatcache(true, $path);
    $check($manual(5, 7) === $a && filemtime($path) === 1700000000, 'Manual crop reuses the matching cache file');
    $check($manual(201, 0) === EVO_SITE_URL . 'source.png', 'Manual crop starting outside the source serves the original');
    $check(file_get_contents($root . 'source.png') === $png, 'Crop leaves the source bytes intact');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
