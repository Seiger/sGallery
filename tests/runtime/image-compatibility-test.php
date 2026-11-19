<?php
declare(strict_types=1);

use EvolutionCMS\Core;
use EvolutionCMS\HelperProcessor;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\Services\NativeImage;
use Seiger\sGallery\sGallery;
use Spatie\Image\Image;

/** Read only the settings needed to isolate a gallery transformation. @since 1.9.0 */
function config($key, $default = null)
{
    return match ($key) {
        'seiger.settings.sGallery.imageCacheDir' => 'assets/cache/',
        'seiger.settings.sGallery.optimize' => false,
        default => $default,
    };
}

/** Keep any gallery state under the temporary fixture directory. @since 1.9.0 */
function storage_path($path = '')
{
    return EVO_BASE_PATH . 'storage/' . $path;
}

/** Return the CMS fixture without starting the site or opening a database. @since 1.9.0 */
function evo()
{
    return $GLOBALS['imageCompatibilityCore'];
}

require __DIR__ . '/bootstrap.php';

if (!class_exists(phpthumb::class) || !class_exists(Image::class)) {
    throw new RuntimeException('Use an Evolution CMS Composer runtime with phpThumb and spatie/image:^3.');
}
if (!extension_loaded('gd') || !(imagetypes() & IMG_WEBP) || !(imagetypes() & IMG_JPG) || !(imagetypes() & IMG_PNG)) {
    throw new RuntimeException('The comparison requires GD with JPEG, PNG and WebP support.');
}

$root = sys_get_temp_dir() . '/sgallery-image-compatibility-' . bin2hex(random_bytes(8));
mkdir($root . '/assets/images', 0700, true);
mkdir($root . '/assets/cache', 0700);
define('EVO_BASE_PATH', $root . '/');
define('EVO_SITE_URL', 'https://image-compatibility.test/');

// The helper prefers the ImageMagick CLI if it finds it. Keep the phpThumb baseline on GD.
$previousPath = getenv('PATH');
putenv('PATH=' . $root);
unset($_SERVER['HTTP_ACCEPT']);

// Record legacy dependency deprecations while keeping the package's strict handler in effect.
$phpThumbDirectory = dirname((new ReflectionClass(phpthumb::class))->getFileName()) . DIRECTORY_SEPARATOR;
$phpThumbDeprecations = [];
$strictHandler = set_error_handler(static function (int $severity, string $message, string $file, int $line)
    use ($phpThumbDirectory, &$phpThumbDeprecations, &$strictHandler): bool {
    if ($severity === E_DEPRECATED && str_starts_with($file, $phpThumbDirectory)) {
        $phpThumbDeprecations[$message] = true;
        return true;
    }

    return $strictHandler($severity, $message, $file, $line);
});

$check = static function (bool $condition, string $label): void {
    if (!$condition) {
        throw new RuntimeException($label);
    }
};

try {
    /** Supply configuration and capture generation errors without bootstrapping Evolution. @since 1.9.0 */
    $GLOBALS['imageCompatibilityCore'] = new class extends Core {
        /** Avoid the application bootstrap for this filesystem-only fixture. @since 1.9.0 */
        public function __construct() {}

        /** Use a private cache directory and retain the caller's other defaults. @since 1.9.0 */
        public function getConfig($name = '', $default = null)
        {
            return $name === 'new_folder_permissions' ? '0700' : $default;
        }

        /** Surface a failed phpThumb generation as a test failure. @since 1.9.0 */
        public function logEvent($evtid, $type, $msg, $source = 'Parser')
        {
            if ((int) $type === 3) {
                throw new RuntimeException(strip_tags($msg));
            }
        }
    };

    $container = new Container();
    Facade::setFacadeApplication($container);
    $container->instance('log', new class {
        /** Keep encoder warnings out of the fixture output. @since 1.9.0 */
        public function warning(string $message): void {}

        /** Surface a failed sGallery generation as a test failure. @since 1.9.0 */
        public function error(string $message): void
        {
            throw new RuntimeException($message);
        }
    });

    $fixture = static function (string $name, int $width, int $height, bool $transparent = false): string {
        $image = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
            imagefilledrectangle($image, 8, 8, $width - 9, $height - 9, imagecolorallocatealpha($image, 25, 150, 35, 60));
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, 220, 60, 30));
            imagefilledrectangle($image, intdiv($width, 3), 0, intdiv($width * 2, 3), $height - 1,
                imagecolorallocate($image, 25, 150, 35));
        }

        $file = 'assets/images/' . $name . '.png';
        imagepng($image, EVO_BASE_PATH . $file);

        return $file;
    };

    $landscape = $fixture('landscape', 96, 48);
    $small = $fixture('small', 48, 24);
    $portrait = $fixture('portrait', 48, 96);
    $alpha = $fixture('alpha', 96, 48, true);
    $mark = $fixture('mark', 16, 16);
    $placeholder = $fixture('noimage', 16, 8);
    $oriented = 'assets/images/oriented.jpg';
    imagejpeg(imagecreatefrompng(EVO_BASE_PATH . $landscape), EVO_BASE_PATH . $oriented);
    // A TIFF orientation tag of 6 makes this landscape JPEG appear as a portrait.
    $exif = "Exif\0\0II" . pack('vVv', 42, 8, 1) . pack('vvVVV', 0x0112, 3, 1, 6, 0);
    $jpeg = file_get_contents(EVO_BASE_PATH . $oriented);
    file_put_contents(EVO_BASE_PATH . $oriented, substr($jpeg, 0, 2) . "\xff\xe1"
        . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));

    $helper = new HelperProcessor(evo());
    $legacy = static fn (string $input, string $options): string => $helper->phpThumb($input, $options, false);
    $gallery = static fn (string $input): sGalleryBuilder => (new sGallery())->file($input)->quality(75);
    $pixel = static fn (GdImage $image, int $x, int $y): array =>
        imagecolorsforindex($image, imagecolorat($image, $x, $y));
    $isWhite = static fn (array $pixel): bool => $pixel['alpha'] < 3
        && min($pixel['red'], $pixel['green'], $pixel['blue']) > 245;
    $isGreen = static fn (array $pixel): bool => $pixel['alpha'] < 3
        && $pixel['green'] > 110 && $pixel['red'] < 80 && $pixel['blue'] < 80;

    $galleryDriver = Image::load(EVO_BASE_PATH . $landscape)->driverName();
    printf("PHP %s, phpThumb GD, sGallery %s, EXIF %s\n", PHP_VERSION, $galleryDriver, extension_loaded('exif') ? 'on' : 'off');

    $render = static function (string $label, callable $generate, array $expected) use ($check): array {
        memory_reset_peak_usage();
        $startMemory = memory_get_usage();
        $startTime = hrtime(true);
        $url = $generate();
        $milliseconds = (hrtime(true) - $startTime) / 1e6;
        $phpPeakBytes = max(0, memory_get_peak_usage() - $startMemory);

        $relative = str_starts_with($url, EVO_SITE_URL) ? substr($url, strlen(EVO_SITE_URL)) : $url;
        $path = EVO_BASE_PATH . rawurldecode($relative);
        $check(is_file($path), "$label produced no file: $url");
        $contents = file_get_contents($path);
        $info = getimagesizefromstring($contents);
        $actual = $info ? [$info[0], $info[1], $info['mime']] : [];
        $check($actual === $expected, "$label: expected " . json_encode($expected) . ', got ' . json_encode($actual));
        $decoded = imagecreatefromstring($contents);
        $check($decoded instanceof GdImage, "$label output is undecodable");

        printf("PASS %-30s %-10s %3dx%-3d %5d B %8.3f ms PHP peak +%d B\n",
            $label, $actual[2], $actual[0], $actual[1], strlen($contents), $milliseconds, $phpPeakBytes);

        return [$url, $path, $decoded];
    };

    if (class_exists(Imagick::class)) {
        $capability = new ReflectionProperty(NativeImage::class, 'imagickEncoders');
        $previous = $capability->getValue();
        try {
            $capability->setValue(null, [IMAGETYPE_WEBP => false] + $previous);
            $render('sGallery GD codec fallback', fn () => $gallery($landscape)
                ->fit('stretch', 39, 21)->format('webp')->getFile(), [39, 21, 'image/webp']);
        } finally {
            $capability->setValue(null, $previous);
        }

        if (defined('Imagick::RESOURCETYPE_WIDTH')) {
            $resource = Imagick::RESOURCETYPE_WIDTH;
            $limit = Imagick::getResourceLimit($resource);
            if ($limit > 120 && Imagick::setResourceLimit($resource, 120)) {
                try {
                    $probe = new Imagick(EVO_BASE_PATH . $landscape);
                    try {
                        $imagickRefused = !$probe->resizeImage(128, 48, Imagick::FILTER_TRIANGLE, 1);
                    } catch (ImagickException) {
                        $imagickRefused = true;
                    }
                    if ($imagickRefused) {
                        $render('sGallery GD render retry', fn () => $gallery($landscape)
                            ->fit('stretch', 128, 48)->format('png')->getFile(), [128, 48, 'image/png']);
                    }
                } finally {
                    Imagick::setResourceLimit($resource, $limit);
                }
            }
        }
    }

    $square = [80, 80, 'image/webp'];
    [, , $legacyFill] = $render('phpThumb far=C landscape', fn () => $legacy($landscape, 'w=80,h=80,far=C,q=75,f=webp'), $square);
    [, , $galleryFill] = $render('sGallery fill landscape', fn () => $gallery($landscape)->fit('fill', 80, 80)->format('webp')->getFile(), $square);
    $check($pixel($legacyFill, 0, 0)['alpha'] > 120, 'GD phpThumb far=C pads WebP with transparency');
    $check($isWhite($pixel($galleryFill, 0, 0)), 'sGallery fill pads WebP with white');
    $check($isGreen($pixel($legacyFill, 40, 40)) && $isGreen($pixel($galleryFill, 40, 40)), 'Both fill modes center the source');

    [, , $legacySmall] = $render('phpThumb far=C small', fn () => $legacy($small, 'w=80,h=80,far=C,q=75,f=webp'), $square);
    [, , $gallerySmall] = $render('sGallery fill small', fn () => $gallery($small)->fit('fill', 80, 80)->format('webp')->getFile(), $square);
    [, , $galleryEnlarged] = $render('sGallery fill-max small', fn () => $gallery($small)->fit('fill-max', 80, 80)->format('webp')->getFile(), $square);
    $check($pixel($legacySmall, 40, 23)['alpha'] > 120 && $isWhite($pixel($gallerySmall, 40, 23)), 'Both small-source fills leave space above the image');
    $check($isGreen($pixel($galleryEnlarged, 40, 23)), 'fill-max enlarges the small source');

    $legacyCropCall = fn () => $legacy($landscape, 'w=80,h=80,zc=C,q=75,f=webp');
    $galleryCropCall = fn () => $gallery($landscape)->fit('crop', 80, 80)->format('webp')->getFile();
    [$legacyUrl, $legacyPath, $legacyCrop] = $render('phpThumb zc=C', $legacyCropCall, $square);
    [$galleryUrl, $galleryPath, $galleryCrop] = $render('sGallery crop', $galleryCropCall, $square);
    $check($isGreen($pixel($legacyCrop, 40, 3)) && $isGreen($pixel($galleryCrop, 40, 3)), 'Both center crops fill the canvas to the top');
    $check($pixel($legacyCrop, 4, 40)['red'] > 170 && $pixel($galleryCrop, 4, 40)['red'] > 170, 'Center crop retains the side color near the edge');
    $check(!str_contains($legacyUrl, '://') && str_starts_with($galleryUrl, EVO_SITE_URL), 'Helper returns a relative URL, Builder returns a site URL');

    foreach ([
        ['phpThumb warm cache', $legacyCropCall, $legacyUrl, $legacyPath],
        ['sGallery warm cache', $galleryCropCall, $galleryUrl, $galleryPath],
    ] as [$label, $call, $url, $path]) {
        $hash = hash_file('sha256', $path);
        $timestamp = 1700000000;
        touch($path, $timestamp);
        [$warmUrl] = $render($label, $call, $square);
        clearstatcache(true, $path);
        $check($url === $warmUrl && filemtime($path) === $timestamp && hash_file('sha256', $path) === $hash,
            "$label rewrote the cached derivative");
    }

    $render('phpThumb one width bound', fn () => $legacy($portrait, 'w_24,q_75,f_webp'), [24, 48, 'image/webp']);
    $render('sGallery resize one bound', fn () => $gallery($portrait)->resize(24)->format('webp')->getFile(), [12, 24, 'image/webp']);
    $render('phpThumb default PNG', fn () => $legacy($landscape, 'w=80,h=80'), [80, 40, 'image/png']);
    $render('sGallery original PNG', fn () => $gallery($landscape)->fit('max', 80, 80)->format('original')->getFile(), [80, 40, 'image/png']);
    $render('phpThumb WebP full size', fn () => $legacy($landscape, 'f=webp,q=75'), [96, 48, 'image/webp']);
    $render('sGallery WebP full size', fn () => $gallery($landscape)->format('webp')->getFile(), [96, 48, 'image/webp']);
    $render('phpThumb explicit JPEG', fn () => $legacy($landscape, 'w=80,h=80,f=jpeg,q=75'), [80, 40, 'image/jpeg']);
    $render('sGallery explicit JPEG', fn () => $gallery($landscape)->fit('max', 80, 80)->format('jpeg')->getFile(), [80, 40, 'image/jpeg']);
    if (imagetypes() & IMG_AVIF) {
        $render('phpThumb explicit AVIF', fn () => $legacy($landscape, 'w=80,h=80,f=avif,q=75'), [80, 40, 'image/avif']);
        $render('sGallery explicit AVIF', fn () => $gallery($landscape)->fit('max', 80, 80)->format('avif')->getFile(), [80, 40, 'image/avif']);
    } else {
        echo "SKIP AVIF comparison: unavailable in the GD build.\n";
    }

    [, , $legacyAlpha] = $render('phpThumb alpha', fn () => $legacy($alpha, 'w=80,h=80,f=webp,q=75'), [80, 40, 'image/webp']);
    [, , $galleryAlpha] = $render('sGallery alpha', fn () => $gallery($alpha)->fit('max', 80, 80)->format('webp')->getFile(), [80, 40, 'image/webp']);
    foreach ([$legacyAlpha, $galleryAlpha] as $image) {
        $check($pixel($image, 0, 0)['alpha'] > 120, 'Outputs preserve a transparent corner');
        $check(abs($pixel($image, 40, 20)['alpha'] - 60) <= 5, 'Outputs preserve partial transparency');
    }

    $legacyOrientation = extension_loaded('exif') ? [20, 40] : [40, 20];
    $galleryOrientation = $galleryDriver === 'imagick' || extension_loaded('exif') ? [20, 40] : [40, 20];
    $render('phpThumb EXIF ar=x', fn () => $legacy($oriented, 'w=40,h=40,ar=x,q=75,f=webp'), [...$legacyOrientation, 'image/webp']);
    $render('sGallery EXIF orientation', fn () => $gallery($oriented)->fit('max', 40, 40)->format('webp')->getFile(), [...$galleryOrientation, 'image/webp']);

    $watermark = rawurlencode('wmi|' . EVO_BASE_PATH . $mark . '|BR|100|3|3');
    [, , $legacyMark] = $render('phpThumb watermark', fn () => $legacy($landscape, "w=80&h=80&f=png&fltr[]=$watermark"), [80, 40, 'image/png']);
    [, , $galleryMark] = $render('sGallery watermark', fn () => $gallery($landscape)->fit('max', 80, 80)->format('png')
        ->watermark($mark, ['position' => 'bottom-right', 'width' => '16px', 'padding' => '3px'])->getFile(), [80, 40, 'image/png']);
    $check($isGreen($pixel($legacyMark, 68, 28)) && $isGreen($pixel($galleryMark, 68, 28)), 'Both watermarks draw the center stripe in the lower right');

    $animations = [
        'gif' => 'R0lGODlhDAAIAPAAAP8AAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQADwAAACwAAAAADAAIAAACCISPqcvtD2MqACH5BAAVAAAALAAAAAAMAAgAgAAA/wAAAAIIhI+py+0PYyoAOw==',
        'webp' => 'UklGRsAAAABXRUJQVlA4WAoAAAACAAAACwAABwAAQU5JTQYAAAD/////AABBTk1GSAAAAAAAAAAAAAsAAAcAAJYAAAJWUDggMAAAANABAJ0BKgwACAACADQloAJ0ugH4AAOwAP7wxAv/ILlhdcjX/yA/5Af8gP/48gAAAEFOTUZEAAAAAAAAAAAACwAABwAA0gAAAFZQOCAsAAAAlAEAnQEqDAAIAAAANCWgAnS6AAOYAP75k2//kB//kB//kB//ID/iF3sgMAA=',
    ];
    foreach ($animations as $format => $bytes) {
        $animationSource = 'assets/images/animation.' . $format;
        $sourcePath = EVO_BASE_PATH . $animationSource;
        file_put_contents($sourcePath, base64_decode($bytes, true));
        $original = EVO_SITE_URL . $animationSource;
        $resizeAnimation = fn (): string => $gallery($animationSource)->format($format)->fit('stretch', 6, 4)->getFile();
        $check($gallery($animationSource)->format('jpeg')->getFile() === $original, 'A still-only output preserves the animated original');
        $url = $resizeAnimation();
        if (!class_exists(Imagick::class) || !NativeImage::canEncode($format, 'imagick')) {
            $check($url === $original, 'GD serves the animated original');
            continue;
        }

        $path = EVO_BASE_PATH . substr($url, strlen(EVO_SITE_URL));
        $check($url !== $original && is_file($path), "Builder generates an animated $format derivative");
        $rendered = new Imagick($path);
        $input = new Imagick($sourcePath);
        $check($rendered->getNumberImages() === 2 && $rendered->getImageWidth() === 6 && $rendered->getImageHeight() === 4,
            "Builder preserves both frames of a resized $format");
        foreach ($rendered as $index => $frame) {
            $input->setIteratorIndex($index);
            $check($frame->getImageDelay() === $input->getImageDelay(), "Builder preserves $format frame delay");
        }
        $singleFrame = imagecreatetruecolor(6, 4);
        $encoder = $format === 'gif' ? 'imagegif' : 'imagewebp';
        $encoder($singleFrame, $path);
        $check($resizeAnimation() === $url && (new Imagick($path))->getNumberImages() === 2,
            "A cached still $format is regenerated as animation");
        echo "PASS $format sequence, timing and cache\n";
    }

    $svg = 'assets/images/icon.svg';
    file_put_contents(EVO_BASE_PATH . $svg, '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $check($legacy($svg, 'w=40,f=webp') === $svg, 'phpThumb returns the original SVG');
    $check($gallery($svg)->resize(40)->format('webp')->getFile() === EVO_SITE_URL . $svg, 'sGallery returns the original SVG');
    $render('phpThumb missing source', fn () => $legacy('assets/images/missing.png', 'f=png'), [16, 8, 'image/png']);
    $check(is_file(EVO_BASE_PATH . $placeholder), 'phpThumb placeholder is preserved');
    $check($gallery('assets/images/missing.png')->resize(40)->getFile() === sGalleryModel::NOIMAGE, 'sGallery returns its missing-image placeholder');

    foreach (array_keys($phpThumbDeprecations) as $message) {
        echo "phpThumb upstream deprecation: $message\n";
    }
    echo "Image compatibility baseline passed.\n";
} finally {
    restore_error_handler();
    putenv($previousPath === false ? 'PATH' : 'PATH=' . $previousPath);
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
