<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\ImageColor;
use Seiger\sGallery\Services\NativeImage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

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

$withSpatie = class_exists(Image::class);
$gd = NativeImage::canEncode('png', 'gd') && NativeImage::canEncode('jpg', 'gd');
$imagick = NativeImage::canEncode('png', 'imagick') && NativeImage::canEncode('jpg', 'imagick');
$lcms = $imagick && preg_match('/\blcms2?\b/i', Imagick::getConfigureOptions('DELEGATES')['DELEGATES'] ?? '');
printf("PHP %s, Spatie %s, GD %s, Imagick %s, LCMS %s\n", PHP_VERSION, $withSpatie ? 'on' : 'off',
    $gd ? 'on' : 'off', $imagick ? 'on' : 'off', $lcms ? 'on' : 'off');
if (!$gd && !$imagick) {
    echo "SKIP color fixtures require PNG and JPEG codecs\n";
    return;
}

$root = sys_get_temp_dir() . '/sgallery-color-' . bin2hex(random_bytes(6)) . '/';
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
    $png = static function (string $metadata = '', array $sample = [180, 90, 70]) use ($chunk): string {
        $type = count($sample) === 4 ? 6 : 2;
        $row = "\0" . str_repeat(pack('C*', ...$sample), 96);
        return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 96, 48, 8, $type, 0, 0, 0))
            . $metadata . $chunk('IDAT', gzcompress(str_repeat($row, 48))) . $chunk('IEND', '');
    };
    $p3 = file_get_contents(__DIR__ . '/../fixtures/DisplayP3-v4.icc');
    $icc = $chunk('iCCP', "Display P3\0\0" . gzcompress($p3));
    $unprofiled = $png();
    file_put_contents($root . 'plain.png', $unprofiled);
    file_put_contents($root . 'p3.png', $png($icc));
    file_put_contents($root . 'p3-alpha.png', $png($icc, [180, 90, 70, 128]));
    file_put_contents($root . 'p3-neutral.png', $png($icc, [150, 150, 150]));
    $neutralAlpha = $png($icc, [150, 150, 150, 128]);
    file_put_contents($root . 'p3-neutral-alpha.png', $neutralAlpha);
    file_put_contents($root . 'linear.png', $png($chunk('gAMA', pack('N', 100000))));

    $jpegPath = $root . 'p3.jpg';
    if ($gd) {
        imagejpeg(imagecreatefromstring($unprofiled), $jpegPath, 100);
    } else {
        $frame = new Imagick($root . 'plain.png');
        $frame->setImageFormat('JPEG');
        $frame->setImageCompressionQuality(100);
        $frame->writeImage($jpegPath);
    }
    $jpeg = file_get_contents($jpegPath);
    $app2 = static fn (string $data): string => "\xff\xe2" . pack('n', strlen($data) + 2) . $data;
    $tagged = substr($jpeg, 0, 2) . $app2("MPF\0")
        . $app2("ICC_PROFILE\0\x01\x02" . substr($p3, 0, 150))
        . $app2("ICC_PROFILE\0\x02\x02" . substr($p3, 150)) . substr($jpeg, 2);
    file_put_contents($jpegPath, $tagged);

    $builder = static fn (string $file, string $format): sGalleryBuilder =>
        (new sGalleryBuilder())->file($file)->format($format)->quality(95)->optimize(false);
    $path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $pixel = static function (string $file, int $x, int $y): array {
        $type = getimagesize($file)[2];
        $decoder = match ($type) {
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_GIF => 'imagecreatefromgif',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            IMAGETYPE_AVIF => 'imagecreatefromavif',
        };
        if (function_exists($decoder)) {
            $image = $decoder($file);
            $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            return [$color['red'], $color['green'], $color['blue'], 1 - $color['alpha'] / 127];
        }
        $sample = (new Imagick($file))->getImagePixelColor($x, $y);
        $color = $sample->getColor();
        return [$color['r'], $color['g'], $color['b'], $sample->getColorValue(Imagick::COLOR_ALPHA)];
    };
    $near = static fn (array $sample, array $expected): bool => max(abs($sample[0] - $expected[0]), abs($sample[1] - $expected[1]), abs($sample[2] - $expected[2])) < 6;
    $hasSrgb = static function (string $file): bool {
        $image = new Imagick($file);
        return $image->getImageProfiles('icc', false) !== [] && $image->getImageProfile('icc') === ImageColor::srgbProfile();
    };

    $plain = $builder('plain.png', 'png')->fit('stretch', 48, 24)->getFile();
    $check($plain !== EVO_SITE_URL . 'plain.png' && $near($pixel($path($plain), 16, 12), [180, 90, 70]), 'Ordinary RGB still renders with its original color');
    if (!$withSpatie) {
        $check($builder('linear.png', 'png')->fit('stretch', 48, 24)->getFile() === EVO_SITE_URL . 'linear.png',
            'A non-sRGB PNG without a supported profile keeps its original color metadata');
    }

    foreach (['p3.png' => 'png', 'p3.jpg' => 'jpg', 'p3-alpha.png' => 'png', 'p3-neutral.png' => 'png', 'p3-neutral-alpha.png' => 'png'] as $source => $format) {
        $render = static fn (): string => $builder($source, $format)->fit('fill', 120, 60)->getFile();
        $url = $render();
        if (!$withSpatie && !$lcms) {
            $check($url === EVO_SITE_URL . $source, "$source remains original without ICC conversion support");
            continue;
        }
        $output = $path($url);
        $info = getimagesize($output);
        $check(array_slice($info, 0, 3) === [120, 60, $format === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG], "$source dimensions and output format");
        $sample = $pixel($output, 60, 30);
        if ($withSpatie) {
            echo "Spatie $source " . json_encode([$sample, $imagick ? (new Imagick($output))->getImageProfiles('icc', false) : []]) . PHP_EOL;
            if ($imagick && $format === 'png') {
                $types = str_starts_with($source, 'p3-neutral') ? [2, 6] : [2, 3, 6];
                $check(in_array(ord(file_get_contents($output)[25]), $types, true) && (new Imagick($output))->getImageProfile('icc') === $p3,
                    "$source keeps compatible PNG samples with its original RGB ICC profile");
                if (str_starts_with($source, 'p3-neutral')) {
                    $check($near($sample, [150, 150, 150]), "$source keeps its neutral RGB pixel values");
                }
                if (str_contains($source, 'alpha')) {
                    $check(abs($sample[3] - 0.5) < 0.03, "$source retains partial alpha with an RGB profile");
                }
            }
            continue;
        }
        $expected = str_starts_with($source, 'p3-neutral') ? [150, 150, 150] : [193, 83, 63];
        $check($near($sample, $expected) && $hasSrgb($output), "$source converts its pixels and retains the sRGB profile after padding");
        $check($near($pixel($output, 2, 2), [255, 255, 255]), "$source has white sRGB padding");
        if (str_contains($source, 'alpha')) {
            $check(abs($sample[3] - 0.5) < 0.03, 'ICC conversion preserves partial alpha');
        }
    }

    if ($withSpatie && $imagick) {
        $writer = new ReflectionMethod(sGalleryBuilder::class, 'saveEncodedImage');
        $descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
        foreach (['p3-neutral.png', 'p3-neutral-alpha.png'] as $source) {
            foreach ([false, true] as $optimize) {
                $url = $builder($source, 'png')->fit('fill', 120, 60)->optimize($optimize)->getFile();
                $output = $path($url);
                $check(in_array(ord(file_get_contents($output)[25]), [2, 6], true) && (new Imagick($output))->getImageProfile('icc') === $p3 &&
                    $near($pixel($output, 60, 30), [150, 150, 150]),
                    "$source optimized=$optimize PNG decodes with RGB ICC and unchanged pixels");
            }
            $image = $builder($source, 'png')->fit('stretch', 41, 23);
            $job = $descriptor->invoke($image, 'png');
            $url = (new sGalleryBuilder())->processOptimization($job);
            $check($image->getFile() === $url && array_slice(getimagesize($path($url)), 0, 2) === [41, 23] &&
                $near($pixel($path($url), 20, 11), [150, 150, 150]) && (new Imagick($path($url)))->getImageProfile('icc') === $p3,
                "$source cold worker publishes the same valid ICC PNG as the frontend");

            $legacy = Image::useImageDriver(ImageDriver::Imagick)->loadFile($root . $source)->fit(Fit::Stretch, 48, 24);
            $legacyOutput = $root . 'legacy-' . $source;
            $writer->invoke($image, $legacy, $legacyOutput, 'png', 90);
            $check(in_array(ord(file_get_contents($legacyOutput)[25]), [2, 6], true) && (new Imagick($legacyOutput))->getImageProfile('icc') === $p3 &&
                $near($pixel($legacyOutput, 24, 12), [150, 150, 150]), "$source legacy Spatie writer preserves its RGB ICC");
        }
        $image = $builder('p3-neutral.png', 'png')->fit('fill', 120, 60);
        $url = $image->getFile();
        $oldUrl = str_replace('-upright-icc.png', '-upright.png', $url);
        $check($oldUrl !== $url, 'Spatie PNG cache identity changes after the ICC correction');
        $oldBytes = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 120, 60, 8, 0, 0, 0, 0)) . $icc
            . $chunk('IDAT', gzcompress(str_repeat("\0" . str_repeat(chr(150), 120), 60))) . $chunk('IEND', '');
        file_put_contents($path($oldUrl), $oldBytes);
        unlink($path($url));
        $check($image->getFile() === $url && $near($pixel($path($url), 60, 30), [150, 150, 150]) &&
            (new Imagick($path($url)))->getImageProfile('icc') === $p3, 'An old grayscale PNG with RGB ICC is regenerated under the corrected key');
        $check(file_get_contents($path($oldUrl)) === $oldBytes, 'ICC cache migration preserves the old file');
        touch($path($url), 1700000000);
        clearstatcache(true, $path($url));
        $check($image->getFile() === $url && filemtime($path($url)) === 1700000000, 'The corrected ICC PNG is reused');
    }

    if (!$withSpatie && $lcms) {
        $flattened = $path($builder('p3-alpha.png', 'jpeg')->fit('stretch', 48, 24)->getFile());
        $check($near($pixel($flattened, 16, 12), [224, 169, 159]) && $hasSrgb($flattened),
            'JPEG composites partial alpha on white after profile conversion');
        if (NativeImage::canEncode('gif', 'imagick')) {
            $gif = $path($builder('p3.png', 'gif')->fit('stretch', 48, 24)->getFile());
            $check(getimagesize($gif)[2] === IMAGETYPE_GIF && $near($pixel($gif, 16, 12), [193, 83, 63]),
                'GIF uses the converted sRGB pixels');
        }
        if (NativeImage::canEncode('webp', 'imagick')) {
            $webp = $path($builder('p3.png', 'webp')->fit('stretch', 48, 24)->getFile());
            $check(getimagesize($webp)[2] === IMAGETYPE_WEBP && $near($pixel($webp, 16, 12), [193, 83, 63]) && $hasSrgb($webp),
                'WebP stores the converted pixels and the sRGB profile');
            $avif = $path($builder('p3.png', 'avif')->fit('stretch', 47, 24)->getFile());
            $type = getimagesize($avif)[2];
            $check(in_array($type, [IMAGETYPE_AVIF, IMAGETYPE_WEBP], true) && $near($pixel($avif, 16, 12), [193, 83, 63]) &&
                $hasSrgb($avif), 'An AVIF request preserves sRGB in the encoded result or WebP fallback');
        }
        if ($gd) {
            $encoders = new ReflectionProperty(NativeImage::class, 'imagickEncoders');
            $original = $encoders->getValue();
            try {
                $encoders->setValue(null, [IMAGETYPE_PNG => false] + $original);
                $check($builder('p3.png', 'png')->fit('stretch', 41, 21)->getFile() === EVO_SITE_URL . 'p3.png',
                    'A profiled source cannot silently fall back to GD');
            } finally {
                $encoders->setValue(null, $original);
            }
        }
    }
    if (!$withSpatie && $imagick) {
        $cmyk = new Imagick($root . 'p3.png');
        $cmyk->removeImageProfile('icc');
        $cmyk->transformImageColorspace(Imagick::COLORSPACE_CMYK);
        $cmyk->setImageFormat('JPEG');
        $cmyk->writeImage($root . 'unprofiled-cmyk.jpg');
        $check((getimagesize($root . 'unprofiled-cmyk.jpg')['channels'] ?? 0) === 4 &&
            $builder('unprofiled-cmyk.jpg', 'jpeg')->fit('stretch', 48, 24)->getFile() === EVO_SITE_URL . 'unprofiled-cmyk.jpg',
            'An unprofiled CMYK JPEG keeps its original samples');
    }
    if (!$withSpatie) {
        file_put_contents($root . 'plain.png', $png($icc));
        $new = $builder('plain.png', 'png')->fit('stretch', 48, 24)->getFile();
        $check($new !== $plain && ($lcms ? $hasSrgb($path($new)) : $new === EVO_SITE_URL . 'plain.png'),
            'A legacy unprofiled cache entry cannot serve an ICC-tagged source');
    }
    $check(file_get_contents($jpegPath) === $tagged, 'The source JPEG profile remains intact');
    $check(file_get_contents($root . 'p3-neutral-alpha.png') === $neutralAlpha, 'The neutral source retains its ICC and alpha bytes');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
