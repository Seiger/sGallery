<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\GalleryFiles;
use Seiger\sGallery\Services\GalleryMedia;
use Seiger\sGallery\Services\ImageColor;
use Seiger\sGallery\Services\NativeImage;

function config($key, $default = null) { return $key === 'seiger.settings.sGallery.optimize' ? false : $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }
function evo()
{
    return new class {
        /** Keep upload permissions explicit in the format fixture. @since 1.9.0 */
        public function getConfig($key, $default = null) { return $GLOBALS['modernUploadConfig'][$key] ?? $default; }
    };
}

require __DIR__ . '/bootstrap.php';
printf("PHP %s, Imagick %s\n", PHP_VERSION, extension_loaded('imagick') ? 'on' : 'off');
$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Report legacy errors without concealing the returned result. @since 1.9.0 */
    public function error(string $message): void { $GLOBALS['modernErrors'][] = $message; }
    /** Accept the AVIF to WebP retry message. @since 1.9.0 */
    public function warning(string $message): void {}
});
$root = sys_get_temp_dir() . '/sgallery-modern-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$modernUploadConfig = ['upload_images' => 'heic,heif,jxl,png', 'upload_media' => '', 'upload_files' => ''];
$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
};

try {
    // The HEIC fixture contains one primary grid image with red and blue halves.
    $heic = base64_decode(
        'AAAAHGZ0eXBoZWljAAAAAG1pZjFoZWljbWlhZgAAAa1tZXRhAAAAAAAAACFoZGxyAAAAAAAAAABw'
        . 'aWN0AAAAAAAAAAAAAAAAAAAAAA5waXRtAAAAAAACAAAAEGlkYXQAAAAAACAAEAAAADhpbG9jAQAA'
        . 'AERAAAIAAQAAAAAAAAHRAAEAAAAAAAAAdwACAAEAAAAAAAAAAQAAAAAAAAAIAAAAOGlpbmYAAAAA'
        . 'AAIAAAAVaW5mZQIAAAEAAQAAaHZjMQAAAAAVaW5mZQIAAAAAAgAAZ3JpZAAAAADYaXBycAAAALZp'
        . 'cGNvAAAAdmh2Y0MBA3AAAAAAAAAAAAAe8AD8/fj4AAAPAyAAAQAYQAEMAf//A3AAAAMAkAAAAwAA'
        . 'AwAeugJAIQABACpCAQEDcAAAAwCQAAADAAADAB6gIIEFluqumubgQEDAgAAAAwCAAAADAIQiAAEA'
        . 'BkQBwXPBiQAAABRpc3BlAAAAAAAAACAAAAAQAAAAFGlzcGUAAAAAAAAAQAAAAEAAAAAQcGl4aQAA'
        . 'AAADCAgIAAAAGmlwbWEAAAAAAAAAAgABAoEDAAICAoQAAAAaaXJlZgAAAAAAAAAOZGltZwACAAEA'
        . 'AQAAAH9tZGF0AAAAcygBrxMhnFJY9Sci//75Mn/v/Kf9693Rf/j1Q+/O9O/8kt2pxvTC6OCNCuw9'
        . 'b3a8iYCFUN2krOYPI6C9qjuEUKfl52JcaHU4Hh0O9FJK5hX/CfCX9nzL85FkRpSoUvF2gJzeT7iG'
        . '6PjEMyLDLxw+ZfQ6+eA=', true,
    );
    // libjxl 0.11.2 lossless output for a 32 x 16 RGBA image, with four opacity bands.
    $jxl = base64_decode('/wrDwUoICBAAFANLGJuccYQDOIADOCBKwDkFAQAgRIAIEAEiQOT5l3v6Hlpn71NVdW+SJAkBdXd3d3f3////W/VmZmZmeP77e/6/oLHmWmvvc29EREQEAWVmZmZmZlVV3fd+d3d3N/y/f89/D40551xrn3uTJEkIqKqqqqqq/v//n3tfd3d3N/y/f89/D40551xrn3uTJEkIqKqqqqqq/v//n3tfd3d39wWkAHAwH6MH7nHLjY7bA9exd5Aej8eox/M8H8LPUwsIQAIK4P/ABg==', true);
    $boxed = "\0\0\0\x0cJXL \x0d\x0a\x87\x0a" . pack('N', 20) . 'ftypjxl ' . str_repeat("\0", 4) . 'jxl '
        . pack('N', strlen($jxl) + 8) . 'jxlc' . $jxl;
    $fixtures = ['heic' => ['HEIC', $heic], 'heif' => ['HEIC', substr_replace($heic, 'mif1', 8, 4)],
        'jxl-raw' => ['JXL', $jxl], 'jxl-boxed' => ['JXL', $boxed]];
    $path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $build = static fn (string $source, string $format = 'png'): sGalleryBuilder =>
        (new sGalleryBuilder())->file($source)->fit('stretch', 16, 8)->format($format)->quality(90)->optimize(false);

    foreach ($fixtures as $label => [$codec, $bytes]) {
        $suffix = $codec === 'JXL' ? 'jxl' : $label;
        $source = "$label.$suffix";
        file_put_contents($root . $source, $bytes);
        $check(NativeImage::imagickInputFormat($root . $source) === $codec, "$label is recognized from its contents");
        $canRead = false;
        if (class_exists(Imagick::class) && Imagick::queryFormats($codec) !== []) {
            try {
                $decoded = new Imagick();
                $canRead = $decoded->readImageBlob($bytes) && $decoded->getNumberImages() === 1;
            } catch (ImagickException) {}
        }
        echo "$label decoder " . ($canRead ? 'on' : 'off') . PHP_EOL;
        $url = $build($source)->getFile();
        $check($build($source)->format('original')->getFile() === EVO_SITE_URL . $source,
            "$label original format returns the source file");
        if (!$canRead || !NativeImage::canEncode('png', 'imagick')) {
            $check($url === EVO_SITE_URL . $source, "$label returns its original URL without the required decoder");
            continue;
        }
        $check($url !== EVO_SITE_URL . $source && array_slice(getimagesize($path($url)), 0, 3) === [16, 8, IMAGETYPE_PNG],
            "$label produces a native PNG derivative");
        $result = new Imagick($path($url));
        if ($codec === 'HEIC') {
            $left = $result->getImagePixelColor(2, 4)->getColor();
            $right = $result->getImagePixelColor(14, 4)->getColor();
            $check($left['r'] > $left['b'] && $right['b'] > $right['r'], "$label keeps its primary image color regions");
        } else {
            foreach ([1.0, 85 / 127, 42 / 127, 0.0] as $i => $alpha) {
                $check(abs($result->getImagePixelColor(4 * $i + 2, 4)->getColorValue(Imagick::COLOR_ALPHA) - $alpha) < 0.03,
                    "$label preserves opacity band $i");
            }
        }
        foreach (['jpeg' => IMAGETYPE_JPEG, 'webp' => IMAGETYPE_WEBP, 'gif' => IMAGETYPE_GIF, 'avif' => IMAGETYPE_AVIF] as $format => $type) {
            $derived = $build($source, $format)->getFile();
            if (!NativeImage::canEncode($format, 'imagick') && !($format === 'avif' && NativeImage::canEncode('webp', 'imagick'))) {
                $check($derived === EVO_SITE_URL . $source, "$label returns the original without a $format encoder");
                continue;
            }
            $type = str_ends_with($derived, '.webp') ? IMAGETYPE_WEBP : $type;
            $check($derived !== EVO_SITE_URL . $source && array_slice(getimagesize($path($derived)), 0, 3) === [16, 8, $type],
                "$label converts to $format or its supported fallback");
            if ($codec === 'JXL' && $format === 'jpeg') {
                $jpeg = new Imagick($path($derived));
                $pixel = $jpeg->getImagePixelColor(14, 4)->getColor();
                $check(min($pixel['r'], $pixel['g'], $pixel['b']) > 235, "$label transparent pixels composite on white for JPEG");
            }
        }
        touch($path($url), 1700000000);
        clearstatcache(true, $path($url));
        $check($build($source)->getFile() === $url && filemtime($path($url)) === 1700000000, "$label reuses its verified cache");
        copy($root . $source, $root . "renamed-$label.bin");
        $check($build("renamed-$label.bin")->getFile() !== EVO_SITE_URL . "renamed-$label.bin", "$label decoding uses the content rather than the filename suffix");
        $image = (new sGalleryBuilder())->file($source)->manualCrop(2, 2, 8, 6)->format('png')->sharpen(4)
            ->watermark($url, ['width' => '2px', 'padding' => 0])->quality(90)->optimize(false);
        $descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
        $job = $descriptor->invoke($image, 'png');
        $queued = (new sGalleryBuilder())->processOptimization($job);
        $check($queued === $image->getFile(), "$label queued geometry uses the same derivative as the frontend");
        $check(file_get_contents($root . $source) === $bytes, "$label source bytes are preserved");
    }

    $allowed = GalleryMedia::classify($root . 'jxl-raw.jxl', 'JXL', 'media');
    $check($allowed === ['type' => 'image', 'extension' => 'jxl'], 'JPEG XL is accepted when CMS upload settings allow it');
    $modernUploadConfig['upload_images'] = 'png';
    try {
        GalleryMedia::classify($root . 'jxl-raw.jxl', 'jxl', 'media');
        throw new LogicException('JPEG XL upload ignored the CMS extension restriction');
    } catch (RuntimeException) {
        echo "PASS JPEG XL upload respects CMS extension restrictions\n";
    }
    file_put_contents($root . 'fake.jxl', 'not an image');
    $check(NativeImage::imagickInputFormat($root . 'fake.jxl') === null, 'A filename suffix cannot disguise invalid contents as JPEG XL');
    file_put_contents($root . 'truncated.jxl', substr($jxl, 0, 16));
    $check($build('truncated.jxl')->getFile() === EVO_SITE_URL . 'truncated.jxl', 'An undecodable JPEG XL returns its original URL');
    // Independently encoded with libjxl: two GIF frames, and a Display P3 PNG.
    file_put_contents($root . 'animated.jxl', base64_decode('/wrBBQFYEwgQoAIAAHQASxKlQgVSYMPFGEzKGGPGGO8AiE0+QLgZqo/k3X0IEKBCAAB0AEsSpUIFUmDDxRhMyhhjxhjvAIhNPkC4GWqP5N19', true));
    if (class_exists(Imagick::class) && Imagick::queryFormats('JXL') !== []) {
        $animation = new Imagick($root . 'animated.jxl');
        $check($animation->getNumberImages() === 2, 'The JPEG XL animation fixture contains two decoded frames');
    }
    $check($build('animated.jxl')->getFile() === EVO_SITE_URL . 'animated.jxl', 'Animated JPEG XL returns its original instead of losing frames');
    file_put_contents($root . 'exif.jxl', base64_decode('AAAADEpYTCANCocKAAAAFGZ0eXBqeGwgAAAAAGp4bCAAAAASanhscAAAAAD/CsMtEBMAAAAuYnJvYkV4aWYbHQAABJxjE0HML/GpCRtwIgFCGuAid4A0qfDb0xfATk4IAAAAwWp4bHCAAAABCAIBALwCSxibnHGEAziAAzggSsA5BQEAIESACBABIkDkeeSevqBz762qqnpJkiQE1N3d3d3dMzPzqv7u7u4uz79/z38PjbXm2nufe5MkSQjoVFVVVf3/////3ve6u7u74f/9e/57aMw551r73JskSUJAVVVVVVX1////3Pu6u7u74f/9e/57aMw551r73JskSUJAVVVVVVX1////3Pu6u7u7LxADAE9/nv2e/s/+AHAXwP8ABw==', true));
    $oriented = (new sGalleryBuilder())->file('exif.jxl')->fit('max', 32, 32)->format('png')->optimize(false)->getFile();
    if (class_exists(Imagick::class) && Imagick::queryFormats('JXL') !== []) {
        $check(array_slice(getimagesize($path($oriented)), 0, 2) === [32, 16], 'JPEG XL does not apply EXIF rotation after decoding its codestream');
    } else {
        $check($oriented === EVO_SITE_URL . 'exif.jxl', 'JPEG XL with EXIF returns the original without its decoder');
    }
    file_put_contents($root . 'p3.jxl', base64_decode('/wrBQQh3CgMSZgGwmevzwHVeR2NUcN3TEyr/rAghqKSQWHwsqwoF1gXMlNFTXpfW2FnSarWGziBA2IxpM7OAT9wVUxmiQ5tpgDS8yZwwd+aMoSOH5Sfk6z/GHe5drTOX+FguDWURGz2Xq4GE8C8pU8GbQzNuJq4MZw6087fZDBkW8qBdK4cHp9s4zQhw0+vfv/MtjzknYF+If//uDfn3b18RVLkgMC448Avb/fn3798dRPEHxvZCyxwsWgwTLRBp7jEOJWZSUO4JuslgRii0sgEIAgEAqAJLGJuccYQDOIADOCBKwDkFAQAgRIAIEAEiQOT/kXv6HlpnV1VVVSVJkhBQd3d3d3f///+/VW9mZmaG57+/5/8LGmuutfY+90ZEREQQUGZmZmZmVlXVfe93d3d3w//79/z30JhzzrX2uTdJkoSAqqqqqqrq////ufd1d3d3w//79/z30JhzzrX2uTdJkoSAqqqqqqrq////ufd1d3d3XyAEAA8095nrCYBvGA==', true));
    $profiled = $build('p3.jxl')->getFile();
    $lcms = class_exists(Imagick::class) && Imagick::queryFormats('JXL') !== [] &&
        preg_match('/\blcms2?\b/i', Imagick::getConfigureOptions('DELEGATES')['DELEGATES'] ?? '');
    if ($lcms) {
        $check($profiled !== EVO_SITE_URL . 'p3.jxl', 'Profiled JPEG XL produces a native derivative');
        $reference = new Imagick($root . 'p3.jxl');
        $reference->profileImage('icc', ImageColor::srgbProfile());
        $actual = new Imagick($path($profiled));
        foreach ([Imagick::COLOR_RED, Imagick::COLOR_GREEN, Imagick::COLOR_BLUE] as $channel) {
            $check(abs($actual->getImagePixelColor(2, 2)->getColorValue($channel) -
                $reference->getImagePixelColor(2, 2)->getColorValue($channel)) < 0.02, 'JPEG XL profile conversion keeps the sRGB channel ' . $channel);
        }
        $check($actual->getImageProfile('icc') === ImageColor::srgbProfile(), 'JPEG XL derivative carries its sRGB profile');
    } else {
        $check($profiled === EVO_SITE_URL . 'p3.jxl', 'Profiled JPEG XL returns the original without decoder and color support');
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
