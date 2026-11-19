<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\Services\NativeImage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

function config($key, $default = null) { return $key === 'seiger.settings.sGallery.optimize' ? false : $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }
function evo()
{
    return new class {
        /** Supply CMS defaults without loading a site or a real queue. @since 1.9.0 */
        public function getConfig($key, $default = null) { return $default; }
    };
}

require __DIR__ . '/bootstrap.php';
$withSpatie = class_exists(Image::class);
printf("PHP %s, Spatie %s, Imagick %s\n", PHP_VERSION, $withSpatie ? 'on' : 'off', extension_loaded('imagick') ? 'on' : 'off');
$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Allow only the legacy Spatie decode failure on a runtime without an HEIF decoder. @since 1.9.0 */
    public function error(string $message): void
    {
        if (empty($GLOBALS['heifAllowDecodeFailure'])) throw new RuntimeException($message);
    }
});
$root = sys_get_temp_dir() . '/sgallery-heif-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
};
$path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
$build = static fn (string $source, string $format = 'png'): sGalleryBuilder =>
    (new sGalleryBuilder())->file($source)->fit('max', 16, 16)->format($format)->quality(90)->optimize(false);
$runJob = static function (sGalleryBuilder $builder, string $format): string {
    $descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
    return (new sGalleryBuilder())->processOptimization($descriptor->invoke($builder, $format));
};
$samples = [[2, 4], [6, 4], [2, 12], [6, 12]];
// The stored quadrants are red/green/blue/yellow. Container rotation is 90 degrees clockwise.
$rgba = [[30, 40, 220, 1 / 3], [230, 30, 20, 1.0], [220, 200, 20, 0.0], [20, 210, 40, 2 / 3]];

try {
    foreach (['heic-irot-alpha.heic', 'heif-irot-exif-alpha.heif'] as $source) {
        $bytes = file_get_contents(__DIR__ . '/../fixtures/' . $source);
        file_put_contents($root . $source, $bytes);
        $check(NativeImage::imagickInputFormat($root . $source) === 'HEIC', "$source is recognized as HEIF input");
        $codec = false;
        if (class_exists(Imagick::class) && Imagick::queryFormats('HEIC') !== []) {
            $stream = fopen($root . $source, 'rb');
            try {
                $decoded = new Imagick();
                // Probe the selected coder: a HEIF alias may have different ImageMagick policy rights.
                $codec = $decoded->setFormat('HEIC') && $decoded->readImageFile($stream);
            } catch (ImagickException) {
                $codec = false;
            } finally {
                fclose($stream);
            }
        }
        $GLOBALS['heifAllowDecodeFailure'] = $withSpatie && !$codec;
        $image = $build($source);
        $url = $image->getFile();
        if (!$codec) {
            $expected = $withSpatie && (!class_exists(Imagick::class) || @getimagesize($root . $source) !== false)
                ? sGalleryModel::NOIMAGE : EVO_SITE_URL . $source;
            $check($url === $expected, "$source keeps its original fallback contract without a decoder");
            $check(file_get_contents($root . $source) === $bytes, "$source fallback preserves the source bytes");
            continue;
        }
        $check($decoded->getNumberImages() === 1 && [$decoded->getImageWidth(), $decoded->getImageHeight()] === [16, 32],
            "$source decoder applies the container rotation exactly once");
        $check($decoded->getImageAlphaChannel(), "$source has a decoded auxiliary alpha image");
        foreach ($samples as $index => [$x, $y]) {
            $check(abs($decoded->getImagePixelColor($x * 2, $y * 2)->getColorValue(Imagick::COLOR_ALPHA) - $rgba[$index][3]) < 0.01,
                "$source fixture opacity quadrant $index");
        }
        if (str_ends_with($source, '.heif')) {
            $exif6 = "II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0\x06\0\0\0\0\0\0\0";
            $check(str_contains($bytes, $exif6), 'The HEIF fixture contains a redundant EXIF orientation of 6');
        }
        if ($withSpatie && @getimagesize($root . $source) === false) {
            $check($url === EVO_SITE_URL . $source, "$source Spatie frontend retains background deferral");
            $url = $runJob($image, 'png');
        }
        $check($url !== EVO_SITE_URL . $source && array_slice(getimagesize($path($url)), 0, 3) === [8, 16, IMAGETYPE_PNG],
            "$source PNG geometry uses the displayed portrait orientation");
        $png = new Imagick($path($url));
        foreach ($samples as $index => [$x, $y]) {
            $color = $png->getImagePixelColor($x, $y);
            $check(abs($color->getColorValue(Imagick::COLOR_ALPHA) - $rgba[$index][3]) < 0.02,
                "$source PNG preserves opacity quadrant $index");
            if ($rgba[$index][3] > 0) {
                foreach ([Imagick::COLOR_RED, Imagick::COLOR_GREEN, Imagick::COLOR_BLUE] as $channelIndex => $channel) {
                    $check(abs(255 * $color->getColorValue($channel) - $rgba[$index][$channelIndex]) < 8,
                        "$source PNG color quadrant $index channel $channelIndex");
                }
            }
        }
        $check(in_array($png->getImageOrientation(), [0, 1], true), "$source PNG orientation cannot trigger another rotation");
        $displayed = clone $png;
        $displayed->autoOrient();
        $check([$displayed->getImageWidth(), $displayed->getImageHeight()] === [8, 16],
            "$source PNG remains portrait when a viewer applies its orientation");
        if ($withSpatie && str_ends_with($source, '.heif')) {
            $legacyUrl = str_replace('-upright.png', '.png', $url);
            $check($legacyUrl !== $url, 'Spatie PNGs use a new cache identity after the orientation correction');
            $check($png->getImageProfiles('exif', false) === [], 'The corrected PNG has no stale EXIF profile');
            $pngBytes = file_get_contents($path($url));
            $chunk = 'eXIf' . $exif6;
            $legacyBytes = substr($pngBytes, 0, 33) . pack('N', strlen($exif6)) . $chunk . pack('N', crc32($chunk)) . substr($pngBytes, 33);
            file_put_contents($path($legacyUrl), $legacyBytes);
            $check((new Imagick($path($legacyUrl)))->getImageOrientation() === 6, 'The seeded old PNG cache contains the stale EXIF rotation');
            $legacyHash = hash_file('sha256', $path($legacyUrl));
            unlink($path($url));
            $frontend = $build($source)->getFile();
            $check($frontend === (@getimagesize($root . $source) === false ? EVO_SITE_URL . $source : $url),
                'The frontend regenerates or defers instead of serving the old rotated PNG cache');
            $check($runJob($image, 'png') === $url && in_array((new Imagick($path($url)))->getImageOrientation(), [0, 1], true),
                'The worker publishes a corrected PNG under the new cache identity');
            $check(hash_file('sha256', $path($legacyUrl)) === $legacyHash, 'Cache migration leaves the previous file untouched');

            // Exercise the legacy Spatie writer as well as the normal BuilderImage writer above.
            $legacyImage = Image::useImageDriver(ImageDriver::Imagick)->loadFile($root . $source);
            $profile = file_get_contents(__DIR__ . '/../fixtures/DisplayP3-v4.icc');
            $legacyImage->image()->setImageProfile('icc', $profile);
            $legacyImage->fit(Fit::Max, 16, 16);
            $writer = new ReflectionMethod(sGalleryBuilder::class, 'saveEncodedImage');
            $writer->invoke($image, $legacyImage, $root . 'legacy-writer.png', 'png', 90);
            $legacyOutput = new Imagick($root . 'legacy-writer.png');
            $check([$legacyOutput->getImageWidth(), $legacyOutput->getImageHeight()] === [8, 16] &&
                in_array($legacyOutput->getImageOrientation(), [0, 1], true), 'The legacy Spatie writer also removes the stale PNG rotation');
            $check($legacyOutput->getImageProfile('icc') === $profile, 'PNG orientation cleanup preserves the ICC profile');
            foreach ($samples as $index => [$x, $y]) {
                $check(abs($legacyOutput->getImagePixelColor($x, $y)->getColorValue(Imagick::COLOR_ALPHA) - $rgba[$index][3]) < 0.02,
                    "Legacy Spatie PNG cleanup preserves opacity quadrant $index");
            }
        }
        $check($runJob($image, 'png') === $url, "$source worker and frontend share the PNG cache");
        touch($path($url), 1700000000);
        clearstatcache(true, $path($url));
        $check($build($source)->getFile() === $url && filemtime($path($url)) === 1700000000,
            "$source reuses the oriented transparent PNG cache");

        $crop = (new sGalleryBuilder())->file($source)->crop('topLeft', 4, 8)->format('png')->quality(90)->optimize(false);
        $cropUrl = $withSpatie ? $runJob($crop, 'png') : $crop->getFile();
        $cropped = new Imagick($path($cropUrl));
        $color = $cropped->getImagePixelColor(2, 4);
        $check([$cropped->getImageWidth(), $cropped->getImageHeight()] === [4, 8] &&
            $color->getColorValue(Imagick::COLOR_BLUE) > 0.8 &&
            abs($color->getColorValue(Imagick::COLOR_ALPHA) - 1 / 3) < 0.02,
            "$source crop uses the displayed top-left blue quadrant and keeps its alpha");

        $jpeg = $build($source, 'jpeg');
        $jpegUrl = $withSpatie ? $runJob($jpeg, 'jpeg') : $jpeg->getFile();
        $check(array_slice(getimagesize($path($jpegUrl)), 0, 3) === [8, 16, IMAGETYPE_JPEG], "$source JPEG keeps portrait geometry");
        $jpg = new Imagick($path($jpegUrl));
        $check(in_array($jpg->getImageOrientation(), [0, 1], true), "$source JPEG cannot be rotated again by its EXIF");
        if (!$withSpatie) {
            foreach ($samples as $index => [$x, $y]) {
                $color = $jpg->getImagePixelColor($x, $y);
                foreach ([Imagick::COLOR_RED, Imagick::COLOR_GREEN, Imagick::COLOR_BLUE] as $channelIndex => $channel) {
                    $expected = $rgba[$index][$channelIndex] * $rgba[$index][3] + 255 * (1 - $rgba[$index][3]);
                    $check(abs(255 * $color->getColorValue($channel) - $expected) < 25,
                        "$source native JPEG composites quadrant $index channel $channelIndex on white");
                }
            }
            $optimized = $build($source)->optimize(true)->getFile();
            $optimizedPng = new Imagick($path($optimized));
            $check([$optimizedPng->getImageWidth(), $optimizedPng->getImageHeight()] === [8, 16] &&
                abs($optimizedPng->getImagePixelColor(2, 4)->getColorValue(Imagick::COLOR_ALPHA) - 1 / 3) < 0.02,
                "$source native optimization keeps the displayed geometry and alpha");
        }
        $check(file_get_contents($root . $source) === $bytes, "$source processing preserves the source bytes and orientation metadata");
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
