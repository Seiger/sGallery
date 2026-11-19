<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\ImageAnimation;
use Seiger\sGallery\Services\NativeImage;
use Spatie\Image\Image;

function config($key, $default = null)
{
    return match ($key) {
        'seiger.settings.sGallery.sharpen' => $GLOBALS['defaultSharpen'],
        'seiger.settings.sGallery.optimize' => false,
        default => $default,
    };
}
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }

require __DIR__ . '/bootstrap.php';

$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Surface unexpected encoder failures in the fixture. @since 1.9.0 */
    public function error(string $message): void { throw new RuntimeException($message); }
});

$withSpatie = class_exists(Image::class);
$gd = NativeImage::canEncode('png', 'gd');
$imagick = NativeImage::canEncode('png', 'imagick');
printf("PHP %s, Spatie %s, GD PNG %s, Imagick PNG %s\n", PHP_VERSION,
    $withSpatie ? 'on' : 'off', $gd ? 'on' : 'off', $imagick ? 'on' : 'off');
if (!$gd && !$imagick) {
    return;
}

$root = sys_get_temp_dir() . '/sgallery-sharpen-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$defaultSharpen = 0;

$check = static function (bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo "PASS $label\n";
};

try {
    // A low-contrast edge leaves room to measure different strengths. The lower rows vary opacity.
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $pixels = '';
    for ($y = 0; $y < 16; $y++) {
        $alpha = [255, 160, 96, 0][intdiv($y, 4)];
        $pixels .= "\0" . str_repeat(pack('C*', 104, 104, 104, $alpha), 12) . str_repeat(pack('C*', 144, 144, 144, $alpha), 12);
    }
    $source = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 24, 16, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress($pixels)) . $chunk('IEND', '');
    file_put_contents($root . 'edge.png', $source);

    $builder = static fn (string $file = 'edge.png'): sGalleryBuilder => (new sGalleryBuilder())->file($file)->format('original')->optimize(false);
    $path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $read = static function (string $file, array $points) use ($gd): array {
        if ($gd) {
            $image = imagecreatefrompng($file);
            return array_map(static function (array $point) use ($image): array {
                $pixel = imagecolorsforindex($image, imagecolorat($image, ...$point));
                return [$pixel['red'], $pixel['green'], $pixel['blue'], 1 - $pixel['alpha'] / 127];
            }, $points);
        }
        $image = new Imagick($file);
        return array_map(static function (array $point) use ($image): array {
            $pixel = $image->getImagePixelColor(...$point);
            $color = $pixel->getColor();
            return [$color['r'], $color['g'], $color['b'], $pixel->getColorValue(Imagick::COLOR_ALPHA)];
        }, $points);
    };

    $mild = $builder()->sharpen(12)->getFile();
    $strong = $builder()->sharpen(37.5)->getFile();
    $check($mild !== EVO_SITE_URL . 'edge.png' && $strong !== $mild && str_contains($strong, '-sh37.5') &&
        array_slice(getimagesize($path($strong)), 0, 3) === [24, 16, IMAGETYPE_PNG],
        'Sharpen alone produces a derivative at the original size and stores fractional strength in the cache key');
    $points = [[11, 1], [12, 1], [11, 5], [12, 10], [11, 13]];
    $base = $read($root . 'edge.png', $points);
    $less = $read($path($mild), $points);
    $more = $read($path($strong), $points);
    $check($less[0][0] < $base[0][0] && $less[1][0] > $base[1][0] &&
        $more[1][0] - $more[0][0] > $less[1][0] - $less[0][0], 'Strength increases contrast on both sides of the edge');
    if (!$withSpatie) {
        foreach ($base as $i => [, , , $alpha]) {
            $check(abs($more[$i][3] - $alpha) < 0.005, "Native sharpening preserves sample $i opacity");
        }
    }

    $render = static fn (float $strength): string => $builder()->fit('stretch', 48, 32)->sharpen($strength)->getFile();
    $zero = $builder()->fit('stretch', 48, 32)->getFile();
    $check($render(0) === $zero && $render(-12) === $zero, 'Zero and negative strength use the unsharpened cache entry');
    $resize = $render(37.5);
    $check($resize !== $zero && array_slice(getimagesize($path($resize)), 0, 3) === [48, 32, IMAGETYPE_PNG], 'Fit and sharpen publish a separate resized derivative');
    $check($render(101) === $render(100), 'Strength above 100 uses the maximum-strength cache entry');
    $defaultSharpen = 12;
    $check($builder()->getFile() === $mild, 'Configured sharpening uses the same cache entry as the explicit strength');
    $defaultSharpen = 0;

    $marked = $builder()->sharpen(37.5)->watermark('edge.png', ['position' => 'top-left', 'width' => 24, 'padding' => 0])->getFile();
    $markPixels = $read($path($marked), array_slice($points, 0, 2));
    $check($markPixels === array_slice($base, 0, 2), 'The watermark is applied after sharpening the base image');

    touch($path($strong), 1700000000);
    clearstatcache(true, $path($strong));
    $check($builder()->sharpen(37.5)->getFile() === $strong && filemtime($path($strong)) === 1700000000, 'Warm sharpened cache is reused without encoding');
    $check(file_get_contents($root . 'edge.png') === $source, 'Sharpening leaves the original file intact');
    if ($withSpatie) {
        $grid = [];
        for ($y = 0; $y < 16; $y++) {
            for ($x = 0; $x < 24; $x++) {
                $grid[] = [$x, $y];
            }
        }
        // Compare the pixels independently of timestamp metadata added by the encoder.
        $digest = hash('sha256', json_encode($read($path($strong), $grid), JSON_THROW_ON_ERROR));
        echo 'Spatie ' . basename($strong) . ' RGBA sha256 ' . $digest . PHP_EOL;
    }

    if (!$withSpatie && NativeImage::canEncode('gif', 'imagick')) {
        $sequence = new Imagick();
        $left = new ImagickDraw();
        $left->setFillColor('rgb(104,104,104)');
        $left->rectangle(0, 0, 11, 15);
        foreach ([144, 156] as $i => $gray) {
            $frame = new Imagick();
            $frame->newImage(24, 16, "rgb($gray,$gray,$gray)", 'GIF');
            $frame->drawImage($left);
            $frame->setImageDelay(13 + 4 * $i);
            $frame->setImageIterations(3);
            $sequence->addImage($frame);
        }
        $sequence->setFirstIterator();
        $sequence->writeImages($root . 'edge.gif', true);
        foreach (['gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP] as $format => $type) {
            if (!NativeImage::canEncode($format, 'imagick')) {
                continue;
            }
            $url = $builder('edge.gif')->format($format)->quality(90)->sharpen(37.5)->getFile();
            $file = $path($url);
            $check($url !== EVO_SITE_URL . 'edge.gif' && ImageAnimation::timing($file, $type) === ['loops' => 3, 'delays' => [130, 170]],
                "Sharpen preserves $format frame count, delays and looping");
            foreach ((new Imagick($file))->coalesceImages() as $frame) {
                $check($frame->getImagePixelColor(11, 8)->getColor()['r'] < 60, "Native sharpening increases edge contrast in each $format frame");
            }
        }
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
