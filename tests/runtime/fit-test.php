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
    /** Surface a failed fit as a test failure. @since 1.9.0 */
    public function error(string $message): void
    {
        throw new RuntimeException($message);
    }
});

$hasGd = NativeImage::canEncode('png', 'gd');
$hasImagick = NativeImage::canEncode('png', 'imagick');
printf("PHP %s, GD %s, Imagick %s\n", PHP_VERSION, $hasGd ? 'on' : 'off', $hasImagick ? 'on' : 'off');

$root = sys_get_temp_dir() . '/sgallery-fit-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');

$check = static function (bool $condition, string $label): void {
    if (!$condition) {
        throw new RuntimeException($label);
    }
    echo "PASS $label\n";
};

try {
    // Odd-sized sources expose centering and rounding. The landscape has red sides,
    // a green middle and a small transparent area at (43–46, 22–26).
    file_put_contents($root . 'landscape.png', base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAGEAAAAxCAYAAADKiMdUAAAAf0lEQVR42u3WMQEAIAwDwYhAAP5VIAQvsDOXqTe8gdyS7IxTVdZsU+luECBAgAABAgQIECBAgAABAgQIECBAgAABAgQIECBAgAABAgQIv8oTBAgQIECAAME7ggABAgQIECBAgAABAgQIECBAgAABAgQIECBAgAABAgQIECC0RbhMOB/RPX1UigAAAABJRU5ErkJggg==', true));
    file_put_contents($root . 'small.png', base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAABcAAAALCAYAAACUPhZAAAAAGUlEQVR42mNgOKDwn2Z41PBRw0cNH8SGAwDpqdlzurymDQAAAABJRU5ErkJggg==', true));
    file_put_contents($root . 'portrait.png', base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAADEAAABhCAYAAAB/EknrAAAAaUlEQVR42u3PMQ0AAAgDsEnBvwqcgQkekh4V0KRr3pOQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJA4tcLAv05BXrpRAAAAAElFTkSuQmCC', true));

    $builder = static fn (string $file): sGalleryBuilder => (new sGalleryBuilder())->file($file);
    if (!$hasGd && !$hasImagick) {
        $check($builder('landscape.png')->fit('fill', 31)->format('png')->getFile() === EVO_SITE_URL . 'landscape.png',
            'The native fit serves the original when neither codec can render it');
        return;
    }

    $derivative = static function (string $url, int $width, int $height, int $type, string $label) use ($root, $check): string {
        $path = $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
        $info = is_file($path) ? getimagesize($path) : false;
        $actual = $info === false ? [] : array_slice($info, 0, 3);
        $check($actual === [$width, $height, $type], "$label size and format ($url, " . json_encode($actual) . ')');
        $check(str_ends_with(pathinfo($path, PATHINFO_FILENAME), '-native'), $label . ' cache namespace');
        return $path;
    };
    $paths = [];
    foreach ([
        ['crop', 'landscape.png', 31, null, 31, 31],
        ['contain', 'small.png', 80, 80, 80, 38],
        ['max', 'small.png', 80, 80, 23, 11],
        ['max', 'portrait.png', 30, 60, 30, 59],
        ['fill', 'small.png', 80, 80, 80, 80],
        ['fill-max', 'small.png', 80, 80, 80, 80],
        ['fill', 'landscape.png', 121, 69, 121, 69],
        ['stretch', 'landscape.png', 31, 23, 31, 23],
    ] as [$mode, $file, $width, $height, $resultWidth, $resultHeight]) {
        $url = $builder($file)->fit($mode, $width, $height)->format('png')->getFile();
        $paths[$mode][$file] = $derivative($url, $resultWidth, $resultHeight, IMAGETYPE_PNG, "$mode $file");
    }

    $pixel = static function (string $path, int $x, int $y) use ($hasGd): array {
        if ($hasGd) {
            $frame = imagecreatefrompng($path);
            $color = imagecolorsforindex($frame, imagecolorat($frame, $x, $y));
            return [$color['red'], $color['green'], $color['blue'], 1 - $color['alpha'] / 127];
        }
        $color = (new Imagick($path))->getImagePixelColor($x, $y);
        return [$color->getColor()['r'], $color->getColor()['g'], $color->getColor()['b'], $color->getColorValue(Imagick::COLOR_ALPHA)];
    };
    $white = static fn (array $rgba): bool => min(array_slice($rgba, 0, 3)) >= 250 && $rgba[3] > 0.98;
    $green = static fn (array $rgba): bool => $rgba[0] < 10 && $rgba[1] > 180 && $rgba[2] < 40 && $rgba[3] > 0.98;
    $check($green($pixel($paths['crop']['landscape.png'], 0, 4)) && $green($pixel($paths['crop']['landscape.png'], 30, 4)),
        'Crop centers the green area and covers the canvas');
    $check($white($pixel($paths['fill']['small.png'], 28, 37)) && $green($pixel($paths['fill']['small.png'], 29, 37)),
        'Fill centers the original-size image with white padding');
    $check($green($pixel($paths['fill-max']['small.png'], 0, 25)), 'Fill-max enlarges the image to the edge of the canvas');
    $check($white($pixel($paths['fill']['landscape.png'], 0, 0)) && $pixel($paths['fill']['landscape.png'], 56, 33)[3] < 0.02,
        'Fill preserves transparency inside the image and leaves the padding opaque');
    file_put_contents($root . 'palette.png', base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAABcAAAALCAMAAACj4OZyAAAABlBMVEUAwCDcABRyCeYDAAAAAnRSTlP/AOW3MEoAAAATSURBVHjaY2AgGTACAS3FBwQAAAwAABE2xBVpAAAAAElFTkSuQmCC', true));
    $palette = $derivative($builder('palette.png')->fit('fill', 80)->format('png')->getFile(),
        80, 80, IMAGETYPE_PNG, 'Indexed PNG fit');
    $check($white($pixel($palette, 0, 0)) && $pixel($palette, 31, 37)[3] < 0.02,
        'Fill preserves the transparent index of a palette PNG');

    $render = static fn (string $mode): string => $builder('landscape.png')->fit($mode, 31)->format('original')->getFile();
    $url = $render('crop');
    $check($render('unknown') === $url, 'An unknown mode uses the crop geometry and cache entry');
    $path = $paths['crop']['landscape.png'];
    touch($path, 1700000000);
    clearstatcache(true, $path);
    $check($render('crop') === $url && filemtime($path) === 1700000000, 'A repeated fit reuses the cached image');

    if (NativeImage::canEncode('jpeg', 'gd') || NativeImage::canEncode('jpeg', 'imagick')) {
        $jpeg = $derivative($builder('small.png')->fit('stretch', 35, 21)->format('jpeg')->quality(85)->getFile(),
            35, 21, IMAGETYPE_JPEG, 'Explicit JPEG fit');
        copy($jpeg, $root . 'photo.jpg');
        $derivative($builder('photo.jpg')->fit('crop', 17, 19)->format('png')->getFile(),
            17, 19, IMAGETYPE_PNG, 'Fit a JPEG source');
    }
    if (NativeImage::canEncode('webp', 'gd') || NativeImage::canEncode('webp', 'imagick')) {
        $_COOKIE['sgallery_supported_image_format'] = 'webp';
        $derivative($builder('portrait.png')->fit('crop', 28)->getFile(), 28, 28, IMAGETYPE_WEBP, 'Negotiated WebP fit');
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
