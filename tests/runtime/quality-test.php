<?php
declare(strict_types=1);

namespace Seiger\sGallery\Builders {
    /** Advance a deterministic clock only for the native deadline regression. @since 1.9.0 */
    function microtime(bool $asFloat = false): float|string
    {
        if (!isset($GLOBALS['qualityClock'])) {
            return \microtime($asFloat);
        }
        $GLOBALS['qualityClock'] += 2.2;
        return $GLOBALS['qualityClock'];
    }
}

namespace {

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\BuilderImage;
use Seiger\sGallery\Services\ImageAnimation;
use Seiger\sGallery\Services\NativeImage;
use Spatie\Image\Image;

function config($key, $default = null)
{
    return match ($key) {
        'seiger.settings.sGallery.quality' => $GLOBALS['configuredQuality'],
        'seiger.settings.sGallery.optimize' => true,
        default => $default,
    };
}
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }

require __DIR__ . '/bootstrap.php';
$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Surface encoder failures in the regression fixture. @since 1.9.0 */
    public function error(string $message): void { throw new RuntimeException($message); }
    /** Accept the expected AVIF fallback warning. @since 1.9.0 */
    public function warning(string $message): void {}
});

$configuredQuality = 100;
$withSpatie = class_exists(Image::class);
$driver = NativeImage::canEncode('webp', 'imagick') ? 'imagick' : 'gd';
printf("PHP %s, Spatie %s, WebP driver %s\n", PHP_VERSION, $withSpatie ? 'on' : 'off', $driver);
if (!NativeImage::canEncode('webp', $driver)) {
    echo "SKIP No native WebP encoder\n";
    return;
}

$root = sys_get_temp_dir() . '/sgallery-quality-' . bin2hex(random_bytes(6)) . '/';
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
    foreach (['noise', 'flat', 'mixed'] as $name) {
        $pixels = '';
        for ($y = 0; $y < 96; $y++) {
            $pixels .= "\0";
            for ($x = 0; $x < 96; $x++) {
                // Deterministic entropy, with no random input or large resource requirement.
                $flat = $name === 'flat' || ($name === 'mixed' && $x >= 48);
                $pixels .= $flat ? "\x50\xa0\xc0" : substr(hash('sha256', "$x,$y", true), 0, 3);
            }
        }
        $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 96, 96, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress($pixels)) . $chunk('IEND', '');
        file_put_contents($root . "$name.png", $png);
    }
    $original = hash_file('sha256', $root . 'noise.png');
    $path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $builder = static function (string $name = 'noise', string $format = 'webp'): sGalleryBuilder {
        $image = (new sGalleryBuilder())->file("$name.png")->fit('stretch', 96, 96)->format($format);
        // Remove wall-clock scheduling from quality comparisons. All calls still use getFile().
        (new ReflectionProperty($image, 'backgroundOptimization'))->setValue($image, true);
        return $image;
    };
    $reference = static function (string $name, int $quality, string $format = 'webp', bool $optimize = false) use ($root, $driver, $withSpatie): string {
        $relative = "reference/$name-$quality-" . (int) $optimize . ".$format";
        if ($withSpatie) {
            $image = BuilderImage::fromSource("$name.png", $root . "$name.png", $format, $driver);
            $image->fit(Spatie\Image\Enums\Fit::Stretch, 96, 96);
            $image->saveNative($root . $relative, $format, $quality, $optimize);
        } else {
            NativeImage::open("$name.png", $format, $driver)->fit('stretch', 96, 96)->optimize($optimize)->save($relative, $quality);
        }
        return $root . $relative;
    };

    foreach ([1, 73, 100] as $quality) {
        $url = $builder()->quality($quality)->getFile();
        $effective = $withSpatie && $quality === 100 ? 99 : $quality;
        $expected = $reference('noise', $effective, optimize: true);
        $check($url !== EVO_SITE_URL . 'noise.png' && hash_file('sha256', $path($url)) === hash_file('sha256', $expected),
            "Explicit WebP quality $quality uses encoder quality $effective without adaptive reduction");
    }
    $check($builder()->quality(0)->getFile() === $builder()->quality(1)->getFile() &&
        $builder()->quality(101)->getFile() === $builder()->quality(100)->getFile(), 'Quality is clamped to the existing 1 through 100 range');

    $fingerprint = static function (string $file) use ($root, $driver): string {
        $frame = NativeImage::open(substr($file, strlen($root)), 'png', $driver)->frame();
        if ($frame instanceof Imagick) {
            return $frame->getImageSignature();
        }
        $hash = hash_init('sha256');
        for ($y = 0; $y < imagesy($frame); $y++) {
            for ($x = 0; $x < imagesx($frame); $x++) {
                $pixel = imagecolorsforindex($frame, imagecolorat($frame, $x, $y));
                hash_update($hash, pack('C*', ...array_values($pixel)));
            }
        }
        return hash_final($hash);
    };
    foreach (['jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'avif' => IMAGETYPE_AVIF, 'gif' => IMAGETYPE_GIF] as $format => $type) {
        if (!NativeImage::canEncode($format, $driver)) {
            continue;
        }
        foreach ([31, 100] as $quality) {
            $url = $builder(format: $format)->quality($quality)->optimize(false)->getFile();
            $expected = $reference('noise', $quality, $format);
            $check((getimagesize($path($url))[2] ?? null) === $type && $fingerprint($path($url)) === $fingerprint($expected),
                "Explicit $format quality $quality matches the selected codec's decoded output");
        }
    }

    $qualities = [99, 97, 95, 93, 92, 91, 90, 89, 88, 87, 86, 85, 84, 82, 80];
    $target = (int) ceil(96 * 96 * 0.9 * (2 / 12)) + 4096;
    foreach (['flat', 'mixed', 'noise'] as $name) {
        if ($withSpatie && $name === 'noise') {
            // The legacy path shares this key with quality(100). Start its adaptive check cold.
            unlink($path($builder()->quality(100)->getFile()));
        }
        $attempts = 0;
        foreach ($qualities as $quality) {
            $expected = $reference($name, $quality, optimize: true);
            $attempts++;
            if (filesize($expected) <= $target) {
                break;
            }
        }
        $url = $builder($name)->getFile();
        $check(hash_file('sha256', $path($url)) === hash_file('sha256', $expected),
            "Adaptive $name WebP matches the first candidate within budget or the last candidate ($quality, $attempts attempts)");
        $stoppedAsExpected = match ($name) {
            'flat' => $attempts === 1,
            'mixed' => $attempts > 1 && $attempts < 15,
            'noise' => $attempts === 15,
        };
        $check($stoppedAsExpected, "$name fixture exercises the intended adaptive stopping point");
        touch($path($url), 1700000000);
        clearstatcache(true, $path($url));
        $check($builder($name)->getFile() === $url && filemtime($path($url)) === 1700000000, 'Adaptive result is reused without another quality search');
        if (!$withSpatie && $name === 'noise') {
            $fixed = $builder()->quality(100)->getFile();
            $check($fixed !== $url, 'Explicit maximum quality has a different cache key from adaptive quality');
        }
    }

    if (!$withSpatie) {
        $configuredQuality = 73;
        $adaptive = $builder()->getFile();
        $fixed = $builder()->quality(73)->getFile();
        $check($fixed !== $adaptive && hash_file('sha256', $path($fixed)) === hash_file('sha256', $reference('noise', 73, optimize: true)),
            'Configured quality and explicit equal quality keep separate adaptive and fixed cache entries');
        $configuredQuality = 100;
        $default = $builder()->optimize(false)->getFile();
        $explicit = $builder()->optimize(false)->quality(100)->getFile();
        $check($default !== $explicit && hash_file('sha256', $path($default)) === hash_file('sha256', $reference('noise', 99)) &&
            hash_file('sha256', $path($explicit)) === hash_file('sha256', $reference('noise', 100)),
            'Native explicit quality 100 stays distinct from default 99 with adaptive selection disabled');
        $only = (new sGalleryBuilder())->file('noise.png')->format('jpeg')->quality(73)->optimize(false)->getFile();
        $check($only !== EVO_SITE_URL . 'noise.png' && (getimagesize($path($only))[2] ?? null) === IMAGETYPE_JPEG,
            'Quality-only conversion works without a geometry request');

        copy($root . 'noise.png', $root . 'deadline.png');
        $timed = $builder('deadline');
        (new ReflectionProperty($timed, 'backgroundOptimization'))->setValue($timed, false);
        $GLOBALS['qualityClock'] = 1000.0;
        try {
            $url = $timed->getFile();
        } finally {
            unset($GLOBALS['qualityClock']);
        }
        $check($url === EVO_SITE_URL . 'deadline.png' && glob($root . 'assets/cache/deadline-*') === [],
            'A deadline between native candidates returns the original and discards staged output');

        if (NativeImage::canEncode('gif', 'imagick')) {
            $sequence = new Imagick();
            foreach (['red', 'blue'] as $i => $color) {
                $frame = new Imagick();
                $frame->newImage(24, 16, $color, 'GIF');
                $frame->setImageDelay(12 + 8 * $i);
                $frame->setImageIterations(3);
                $sequence->addImage($frame);
            }
            $sequence->setFirstIterator();
            $sequence->writeImages($root . 'sequence.gif', true);
            $animation = static fn (): sGalleryBuilder => (new sGalleryBuilder())->file('sequence.gif')->format('webp');
            $url = $animation()->getFile();
            $fixed = $animation()->quality(100)->getFile();
            $check($url !== $fixed && ImageAnimation::timing($path($url), IMAGETYPE_WEBP) === ['loops' => 3, 'delays' => [120, 200]] &&
                ImageAnimation::timing($path($fixed), IMAGETYPE_WEBP) === ['loops' => 3, 'delays' => [120, 200]],
                'Animated WebP quality selection preserves the original timing with separate fixed and adaptive cache entries');

            $sequence = new Imagick();
            foreach ([0, 1] as $i) {
                $frame = new Imagick($root . 'noise.png');
                $frame->setImageFormat('GIF');
                $frame->setImageDelay(12 + 8 * $i);
                $frame->setImageIterations(3);
                $sequence->addImage($frame);
            }
            $sequence->setFirstIterator();
            $sequence->writeImages($root . 'sequence-noise.gif', true);
            $animation = static function (): sGalleryBuilder {
                $image = (new sGalleryBuilder())->file('sequence-noise.gif')->format('webp');
                (new ReflectionProperty($image, 'backgroundOptimization'))->setValue($image, true);
                return $image;
            };
            $url = $animation()->getFile();
            $fixed = $animation()->quality(80)->getFile();
            $check(filesize($path($fixed)) > $target && hash_file('sha256', $path($url)) === hash_file('sha256', $path($fixed)),
                'Animated WebP exhausts the candidate list at quality 80 when the size target cannot be met');
            $oldUrl = str_replace('-adaptive-native', '-native', $url);
            $check($oldUrl !== $url, 'Adaptive animation has a different cache key from the previous single-quality renderer');
            copy($path($animation()->quality(99)->getFile()), $path($oldUrl));
            unlink($path($url));
            $check($animation()->getFile() === $url && hash_file('sha256', $path($url)) === hash_file('sha256', $path($fixed)),
                'A cached animation from the previous renderer cannot bypass the adaptive search');
        }
    }
    $check(hash_file('sha256', $root . 'noise.png') === $original, 'Quality selection leaves the original file intact');
    $check(glob($root . 'assets/cache/*pending*') === [] && glob($root . 'assets/cache/*.webp.webp') === [],
        'Quality selection leaves no staged files in the cache');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
}
