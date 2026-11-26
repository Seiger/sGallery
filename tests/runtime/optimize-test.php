<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\ImageAnimation;
use Seiger\sGallery\Services\ImageColor;
use Seiger\sGallery\Services\NativeImage;

function config($key, $default = null)
{
    return $key === 'seiger.settings.sGallery.optimize' ? false : $default;
}
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }

require __DIR__ . '/bootstrap.php';
$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Surface unexpected output failures in the isolated fixture. @since 1.9.0 */
    public function error(string $message): void { throw new RuntimeException($message); }
    /** Accept the expected AVIF to WebP retry on builds without AVIF. @since 1.9.0 */
    public function warning(string $message): void {}
});
$driver = NativeImage::canEncode('png', 'imagick') ? 'imagick' : 'gd';
printf("PHP %s, PNG driver %s\n", PHP_VERSION, $driver);
if (!NativeImage::canEncode('png', $driver)) {
    echo "SKIP No PNG encoder\n";
    return;
}

$root = sys_get_temp_dir() . '/sgallery-optimize-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
mkdir($root . 'reference', 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$check = static function (bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo "PASS $label\n";
};

try {
    $chunk = static fn (string $type, string $bytes): string => pack('N', strlen($bytes)) . $type . $bytes . pack('N', crc32($type . $bytes));
    $row = "\0";
    foreach ([[240, 20, 30, 0], [20, 240, 30, 85], [20, 30, 240, 170], [230, 230, 40, 255]] as $rgba) {
        $row .= str_repeat(pack('C*', ...$rgba), 24);
    }
    $header = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 96, 48, 8, 6, 0, 0, 0));
    $text = 'native-optimize-fixture-' . str_repeat('service metadata ', 256);
    $data = $chunk('IDAT', gzcompress(str_repeat($row, 48))) . $chunk('IEND', '');
    file_put_contents($root . 'source.png', $header . $chunk('tEXt', "Description\0" . $text) . $data);
    $sourceHash = hash_file('sha256', $root . 'source.png');
    $build = static fn (string $format = 'png', string $source = 'source.png'): sGalleryBuilder =>
        (new sGalleryBuilder())->file($source)->fit('stretch', 96, 48)->format($format)->quality(90);
    $path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $pixels = static function (string $file) use ($root, $driver): array {
        $frame = NativeImage::open(substr($file, strlen($root)), 'png', $driver)->frame();
        $samples = [];
        foreach ([12, 36, 60, 84] as $x) {
            if ($frame instanceof Imagick) {
                $pixel = $frame->getImagePixelColor($x, 24);
                $c = $pixel->getColor();
                $samples[] = [$c['r'], $c['g'], $c['b'], round($pixel->getColorValue(Imagick::COLOR_ALPHA), 3)];
            } else {
                $pixel = imagecolorsforindex($frame, imagecolorat($frame, $x, 24));
                $samples[] = [$pixel['red'], $pixel['green'], $pixel['blue'], round(1 - $pixel['alpha'] / 127, 3)];
            }
        }
        return $samples;
    };

    $plain = $build()->optimize(false)->getFile();
    $optimized = $build()->optimize()->getFile();
    $check($optimized !== $plain && array_slice(getimagesize($path($optimized)), 0, 3) === [96, 48, IMAGETYPE_PNG],
        'Optimize uses its own cache entry and produces a decoded PNG');
    $check($pixels($path($optimized)) === $pixels($path($plain)), 'Native PNG optimization preserves RGB samples and partial transparency');
    $check(filesize($path($optimized)) < filesize($path($plain)), 'Native compression reduces the PNG fixture size');
    $check(!str_contains(file_get_contents($path($optimized)), 'native-optimize-fixture-'), 'Native optimized PNG excludes the source description');
    $alone = (new sGalleryBuilder())->file('source.png')->format('original')->optimize()->getFile();
    $check($alone !== EVO_SITE_URL . 'source.png' && (getimagesize($path($alone))[2] ?? null) === IMAGETYPE_PNG,
        'Optimize alone generates a derivative at the source dimensions');
    $old = str_replace('-opt-enc', '-opt', $optimized);
    $check($old !== $optimized, 'Native optimized cache has a new key for the encoder policy');
    copy($path($plain), $path($old));
    unlink($path($optimized));
    $check($build()->optimize()->getFile() === $optimized && filesize($path($optimized)) < filesize($path($old)),
        'An older unoptimized cache entry cannot bypass native optimization');
    touch($path($optimized), 1700000000);
    clearstatcache(true, $path($optimized));
    $check($build()->optimize()->getFile() === $optimized && filemtime($path($optimized)) === 1700000000,
        'A verified optimized derivative is reused without encoding');
    $reused = $build()->optimize();
    $check($reused->getFile() === $optimized && $reused->optimize(false)->getFile() === $plain,
        'Optimize can be disabled on the same Builder');

    foreach (['jpeg' => IMAGETYPE_JPEG, 'avif' => IMAGETYPE_AVIF, 'webp' => IMAGETYPE_WEBP, 'gif' => IMAGETYPE_GIF] as $format => $type) {
        if (!NativeImage::canEncode($format, $driver)) {
            continue;
        }
        $url = $build($format)->optimize()->getFile();
        $expectedType = $format === 'avif' && str_ends_with($url, '.webp') ? IMAGETYPE_WEBP : $type;
        $check(array_slice(getimagesize($path($url)), 0, 3) === [96, 48, $expectedType],
            "Optimized $format produces the requested type or its supported fallback");
        if ($format === 'jpeg') {
            $check((getimagesize($path($url))['bits'] ?? null) === 8 && str_contains(file_get_contents($path($url)), "\xff\xc2"),
                'Native optimized JPEG uses progressive encoding');
        }
    }

    if ($driver === 'imagick') {
        $profile = ImageColor::srgbProfile();
        $profiled = $header . $chunk('iCCP', "sRGB\0\0" . gzcompress($profile)) . $data;
        file_put_contents($root . 'profiled.png', $profiled);
        foreach (['png', 'jpeg', 'webp', 'avif'] as $format) {
            if (!NativeImage::canEncode($format, 'imagick')) continue;
            $url = $build($format, 'profiled.png')->optimize()->getFile();
            $decoded = new Imagick($path($url));
            $check($url !== EVO_SITE_URL . 'profiled.png' && $decoded->getImageProfile('icc') === $profile,
                "Native $format metadata removal preserves the sRGB ICC profile");
        }

        if (NativeImage::canEncode('gif', 'imagick')) {
            $sequence = new Imagick();
            foreach (['blue', 'lime', 'lime'] as $i => $color) {
                $frame = new Imagick();
                $frame->newImage(96, 48, $color, 'GIF');
                $frame->setImageDelay([12, 20, 7][$i]);
                $frame->setImageIterations(3);
                $frame->setImageProperty('comment', $text);
                $sequence->addImage($frame);
            }
            $sequence->setFirstIterator();
            $sequence->writeImages($root . 'sequence.gif', true);
            foreach (['gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP] as $format => $type) {
                if (!NativeImage::canEncode($format, 'imagick')) continue;
                $url = $build($format, 'sequence.gif')->optimize()->getFile();
                $check($url !== EVO_SITE_URL . 'sequence.gif' && ImageAnimation::timing($path($url), $type) === ['loops' => 3, 'delays' => [120, 200, 70]],
                    "Native optimized $format preserves duplicate frames, delays and loop count");
                $check(!str_contains(file_get_contents($path($url)), 'native-optimize-fixture-'), "Native optimized $format strips comments");
            }
        }
    }
    $check(hash_file('sha256', $root . 'source.png') === $sourceHash, 'Optimization leaves the source file intact');
    $check(glob($root . 'assets/cache/*pending*') === [] && glob($root . 'assets/cache/.sgallery-image-*') === [],
        'Optimization leaves no staged files behind');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
