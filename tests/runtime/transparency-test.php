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
    /** Report fixture rendering failures. @since 1.9.0 */
    public function error(string $message): void
    {
        throw new RuntimeException($message);
    }
});

$gd = NativeImage::canEncode('png', 'gd') && NativeImage::canEncode('jpg', 'gd');
$imagick = NativeImage::canEncode('png', 'imagick') && NativeImage::canEncode('jpg', 'imagick');
printf("PHP %s, GD %s, Imagick %s\n", PHP_VERSION,
    $gd ? 'on' : 'off', $imagick ? 'on' : 'off');
if (!$gd && !$imagick) {
    echo "SKIP transparency fixtures require PNG and JPEG support\n";
    return;
}

$root = sys_get_temp_dir() . '/sgallery-transparency-' . bin2hex(random_bytes(6)) . '/';
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
    $bandedPng = static function (int $colorType, array $pixels, string $metadata = '') use ($chunk): string {
        $row = "\0";
        foreach ($pixels as $pixel) {
            $row .= str_repeat(pack('C*', ...$pixel), 32);
        }
        return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 96, 48, 8, $colorType, 0, 0, 0)) .
            $metadata . $chunk('IDAT', gzcompress(str_repeat($row, 48))) . $chunk('IEND', '');
    };
    // Red is transparent, blue is partially transparent, and green is opaque.
    $rgba = $bandedPng(6, [[255, 0, 0, 0], [0, 0, 255, 128], [0, 255, 0, 255]]);
    $indexed = $bandedPng(3, [[0], [1], [2]], $chunk('PLTE', "\xff\0\0\0\0\xff\0\xff\0") . $chunk('tRNS', "\0\x80"));
    $key = $bandedPng(2, [[255, 0, 0], [0, 0, 255], [0, 255, 0]], $chunk('tRNS', pack('n*', 255, 0, 0)));
    $opaque = $bandedPng(2, [[255, 0, 0], [0, 0, 255], [0, 255, 0]]);
    foreach (['rgba' => $rgba, 'indexed' => $indexed, 'color-key' => $key, 'opaque' => $opaque] as $name => $bytes) {
        file_put_contents("$root$name.png", $bytes);
    }

    $builder = static fn (string $name): sGalleryBuilder => (new sGalleryBuilder())->file("$name.png")->optimize(false)->quality(95);
    $render = static fn (string $name, string $format): string => $builder($name)->fit('stretch', 48, 24)->format($format)->getFile();
    $path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $pixels = static function (string $path, array $points): array {
        $type = getimagesize($path)[2];
        $decoder = match ($type) {
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
        };
        $frame = function_exists($decoder) ? $decoder($path) : new Imagick($path);
        return array_map(static function (array $point) use ($frame): array {
            if ($frame instanceof GdImage) {
                $color = imagecolorsforindex($frame, imagecolorat($frame, ...$point));
                return [$color['red'], $color['green'], $color['blue'], 1 - $color['alpha'] / 127];
            }
            $pixel = $frame->getImagePixelColor(...$point);
            $color = $pixel->getColor();
            return [$color['r'], $color['g'], $color['b'], $pixel->getColorValue(Imagick::COLOR_ALPHA)];
        }, $points);
    };
    $isColor = static fn (array $sample, array $expected): bool => max(abs($sample[0] - $expected[0]), abs($sample[1] - $expected[1]), abs($sample[2] - $expected[2])) < 25;
    $points = [[8, 12], [24, 12], [40, 12]];
    $webp = NativeImage::canEncode('webp', 'gd') || NativeImage::canEncode('webp', 'imagick');
    $formats = $webp ? ['png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP] : ['png' => IMAGETYPE_PNG];

    foreach (['rgba', 'indexed', 'color-key'] as $source) {
        foreach ($formats as $format => $type) {
            $output = $path($render($source, $format));
            $check(array_slice(getimagesize($output), 0, 3) === [48, 24, $type], "$source $format dimensions and format");
            $samples = $pixels($output, $points);
            $expectedMiddle = $source === 'color-key' ? 1.0 : 0.5;
            $check($samples[0][3] < 0.03 && abs($samples[1][3] - $expectedMiddle) < 0.04 && $samples[2][3] > 0.97,
                "$source $format preserves alpha");
        }
        $jpeg = $path($render($source, 'jpeg'));
        $check(array_slice(getimagesize($jpeg), 0, 3) === [48, 24, IMAGETYPE_JPEG], "$source JPEG dimensions and format");
        $samples = $pixels($jpeg, $points);
        $middle = $source === 'color-key' ? [0, 0, 255] : [127, 127, 255];
        $check($isColor($samples[0], [255, 255, 255]) && $isColor($samples[1], $middle) && $isColor($samples[2], [0, 255, 0]),
            "$source JPEG uses white and blends partial alpha");
    }

    $padded = $path($builder('color-key')->fit('fill', 120, 60)->format('png')->getFile());
    [$padding, $inside] = $pixels($padded, [[4, 4], [25, 15]]);
    $check($isColor($padding, [255, 255, 255]) && $padding[3] > 0.97 && $inside[3] < 0.03,
        'White fill padding preserves the transparent region inside the image');

    $_SESSION['supported_image_format'] = 'jpg';
    $_COOKIE['sgallery_supported_image_format'] = 'jpg';
    $auto = $path($builder('indexed')->fit('stretch', 48, 24)->getFile());
    $check(getimagesize($auto)[2] === IMAGETYPE_PNG && $pixels($auto, $points)[0][3] < 0.03,
        'Auto format preserves alpha despite a cached JPEG preference');
    $check(getimagesize($path($builder('opaque')->fit('stretch', 48, 24)->getFile()))[2] === IMAGETYPE_JPEG,
        'An alpha-preserving PNG fallback does not change the cached JPEG preference for opaque images');

    $_GET['sgallery-format'] = 'jpg';
    $override = $path($builder('indexed')->fit('stretch', 48, 24)->getFile());
    unset($_GET['sgallery-format']);
    $check(getimagesize($override)[2] === IMAGETYPE_JPEG && $isColor($pixels($override, $points)[0], [255, 255, 255]),
        'An explicit JPEG preference keeps white compositing');

    file_put_contents($root . 'cached.png', $opaque);
    $stored = $render('cached', 'jpg');
    file_put_contents($root . 'cached.png', $rgba);
    $corrected = $render('cached', 'jpg');
    $check($stored !== $corrected && is_file($path($stored)) &&
        $isColor($pixels($path($corrected), $points)[0], [255, 255, 255]) && $render('cached', 'jpg') === $corrected,
        'The new JPEG result bypasses the old cache key and is reused');
    $check(file_get_contents($root . 'rgba.png') === $rgba, 'The source PNG is preserved');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
