<?php
declare(strict_types=1);

use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\NativeImage;
use Seiger\sGallery\Services\PhpThumbOptions;
use Seiger\sGallery\sGallery;

function config($key, $default = null) { return $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }
function evo() {
    return new class {
        public function getConfig($key, $default = null) { return $default; }
    };
}

require __DIR__ . '/bootstrap.php';

$root = sys_get_temp_dir() . '/sgallery-phpthumb-' . bin2hex(random_bytes(6)) . '/';
mkdir($root . 'assets/images', 0700, true);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://thumbnail.test/site/');
$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
};
$path = static fn (string $url): string => $root . rawurldecode($url);
$info = static fn (string $url): array => array_slice(@getimagesize($path($url)) ?: [], 0, 3);
$thumb = (new sGallery())->phpThumb(...);
$pixel = static function (string $url, int $x, int $y) use ($path): array {
    $image = imagecreatefromstring(file_get_contents($path($url)));
    return imagecolorsforindex($image, imagecolorat($image, $x, $y));
};
$make = static function (string $name, int $width, int $height) use ($root): void {
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, 0xee2211);
    imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, 0x22cc11);
    imagepng($image, $root . $name);
};
$can = static fn (string $format): bool => NativeImage::canEncode($format, 'gd') || NativeImage::canEncode($format, 'imagick');

try {
    if (!function_exists('imagecreatetruecolor')) {
        $source = 'assets/images/sample.png';
        file_put_contents($path($source), base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAABcAAAALCAYAAACUPhZAAAAAGUlEQVR42mNgOKDwn2Z41PBRw0cNH8SGAwDpqdlzurymDQAAAABJRU5ErkJggg==', true));
        $result = $thumb($source, 'w=10,h=10,far=C', false);
        if ($can('png')) {
            $check($result !== $source && $info($result) === [10, 10, IMAGETYPE_PNG], 'Imagick alone generates a padded PNG');
            $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
            if (is_readable($font)) {
                copy($font, $root . 'assets/images/font.ttf');
                $text = $thumb($source, 'f=png&fltr[]=' . rawurlencode('wmt|OK|6|TL|ffffff|assets/images/font.ttf|100|0'), false);
                $check($text !== $source && $info($text) === [23, 11, IMAGETYPE_PNG], 'Imagick alone draws a local TrueType watermark');
            }
        } else {
            $check($result === $source, 'Without image drivers the helper returns the original');
        }
        return;
    }

    $source = 'assets/images/photo ü.png';
    $encodedSource = 'assets/images/photo%20%C3%BC.png';
    $make($source, 96, 48);
    $make('assets/images/portrait.png', 48, 96);
    $make('assets/images/square.png', 48, 48);
    $make('assets/images/small.png', 24, 12);
    $make('assets/images/noimage.png', 8, 8);
    file_put_contents($root . 'assets/images/icon ü.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $check($thumb('assets/images/icon%20%C3%BC.svg', 'w=32,dpi=200') === 'assets/images/icon%20%C3%BC.svg', 'Local SVG keeps its encoded URL');
    $check($thumb('https://images.test/photo.svg', 'w=30') === 'https://images.test/photo.svg', 'Remote URL is returned to the browser');

    $base = $thumb($encodedSource, 'w=40,h=40', false);
    $check(str_starts_with($base, 'assets/cache/') && str_contains($base, '%20%C3%BC') &&
        $info($base) === [40, 20, IMAGETYPE_PNG], 'Site-relative derivative uses source format and encodes its filename');
    $check($thumb($source, 'w_10,h_40,w_40', false) === $base &&
        $thumb($source, 'h=40&w=40&src=unused', false) === $base, 'Legacy spelling, repeated scalars and ignored keys share a cache key');
    $check($thumb($source, 'w=40&h=40&f=unsupported', false) === $base, 'Unsupported format uses the source default');
    touch($path($base), 1700000000);
    $check($thumb($source, 'w=40,h=40', false) === $base && filemtime($path($base)) === 1700000000, 'Warm cache is reused');
    $check($info($thumb($source, 'h=20', false)) === [40, 20, IMAGETYPE_PNG], 'Height-only geometry keeps source proportions');
    $check($info($thumb('assets/images/small.png', 'w=40', false)) === [24, 12, IMAGETYPE_PNG] &&
        $info($thumb('assets/images/small.png', 'w=40,aoe=1', false)) === [40, 20, IMAGETYPE_PNG], 'Single width and enlargement flag');
    $options = 'w=21,h=21,wp=14,hp=30,wl=32,hl=20,ws=17,hs=19';
    $check($info($thumb($source, $options, false)) === [32, 16, IMAGETYPE_PNG] &&
        $info($thumb('assets/images/portrait.png', $options, false)) === [14, 28, IMAGETYPE_PNG] &&
        $info($thumb('assets/images/square.png', $options, false)) === [17, 17, IMAGETYPE_PNG], 'Orientation-specific width and height');
    $check($info($thumb($source, 'sx=0.5,sy=0.25,sw=0.5,sh=0.5,w=24', false)) === [24, 12, IMAGETYPE_PNG], 'Fractional crop precedes resizing');
    $check($pixel($thumb($source, 'sx=48,sw=48,sh=24,w=24', false), 4, 4)['green'] > 150, 'Pixel crop uses the requested source rectangle');

    $far = $thumb('assets/images/small.png', 'w=40,h=40,far=C', false);
    $check($info($far) === [40, 40, IMAGETYPE_PNG] && $pixel($far, 0, 0)['alpha'] === 127 &&
        $pixel($far, 10, 16)['alpha'] === 0, 'Centered far pads with transparency and retains the small source');
    $border = $thumb($source, 'w=40,h=40,far=T,bc=0000FF', false);
    $check($pixel($border, 4, 4)['red'] > 180 && $pixel($border, 4, 38)['blue'] === 255, 'Position and border color affect the padded canvas');
    $painted = $thumb($source, 'w=40,h=40,far=C,bg=0000FF', false);
    $padding = $pixel($painted, 0, 0);
    $check($padding['blue'] === 255 && $padding['alpha'] === 0, 'Explicit background makes the padding opaque');
    $zc = $thumb($source, 'w=30,h=30,zc=R,iar=1,far=TL', false);
    $check($info($zc) === [30, 30, IMAGETYPE_PNG] && $pixel($zc, 3, 3)['green'] > 150, 'Positioned zoom crop takes precedence over stretch and padding');
    $iar = $thumb($source, 'w=30,h=30,iar=1,far=TL', false);
    $check($info($iar) === [30, 30, IMAGETYPE_PNG] && $pixel($iar, 4, 28)['red'] > 180, 'Exact stretch takes precedence over padding');
    $check($info($thumb($source, 'ra=90', false)) === [48, 96, IMAGETYPE_PNG] &&
        $info($thumb($source, 'ar=p', false)) === [48, 96, IMAGETYPE_PNG], 'Explicit rotation and forced portrait orientation');

    $trimmed = imagecreatetruecolor(32, 24);
    imagefill($trimmed, 0, 0, 0xffffff);
    imagefilledrectangle($trimmed, 4, 3, 27, 20, 0xff0000);
    imagepng($trimmed, $root . 'assets/images/trim.png');
    $check($info($thumb('assets/images/trim.png', 'ica=3', false)) === [24, 18, IMAGETYPE_PNG] &&
        $info($thumb('assets/images/trim.png', 'ica=5|0.01|FFFFFF', false)) === [24, 18, IMAGETYPE_PNG], 'Automatic trimming by background mode and color');

    $ordered = $thumb($source, 'fltr[]=flip|x&fltr[]=crop|0|48|0|0', false);
    $reversed = $thumb($source, 'fltr[]=crop|0|48|0|0&fltr[]=flip|x', false);
    $check($info($ordered) === [48, 48, IMAGETYPE_PNG] && $ordered !== $reversed &&
        $pixel($ordered, 0, 0)['green'] > 150 && $pixel($reversed, 0, 0)['red'] > 180, 'Filter execution and cache preserve operation order');
    $gray = $pixel($thumb($source, 'fltr[]=gray', false), 4, 4);
    $negative = $pixel($thumb($source, 'fltr[]=neg', false), 4, 4);
    $brighter = $pixel($thumb($source, 'fltr[]=brit|40', false), 4, 4);
    $gamma = $pixel($thumb($source, 'fltr[]=gam|2', false), 4, 4);
    $contrast = $pixel($thumb($source, 'fltr[]=cont|100', false), 4, 4);
    $check($gray['red'] === $gray['green'] && $gray['green'] === $gray['blue'] &&
        $negative['red'] < 40 && $negative['blue'] > 210 &&
        $brighter['green'] > 34 && $gamma['green'] > 34 &&
        $contrast['red'] > 238 && $contrast['green'] < 34, 'Color filters change the intended channels');
    $check($info($thumb($source, 'fltr[]=rot|90&fltr[]=size|36||1', false)) === [36, 96, IMAGETYPE_PNG],
        'Resize filter keeps the omitted dimension when stretching a rotated image');
    $check($thumb($source, 'w=40&bgt=1&dpi=400', false) === $thumb($source, 'w=40', false), 'Unassigned raster options leave output unchanged');
    $check($thumb($source, 'w=40&fltr[]=unknown', false) === $encodedSource, 'Unsupported filters retain the original');

    $mark = imagecreatetruecolor(4, 4);
    imagefill($mark, 0, 0, 0x0000ff);
    $markPath = 'assets/images/mark.png';
    imagepng($mark, $root . $markPath);
    $markOptions = 'w=48&fltr[]=wmi|' . $markPath . '|BR|100|2|3';
    $marked = $thumb($source, $markOptions, false);
    $check($pixel($marked, 43, 18)['blue'] === 255, 'Local image watermark uses independent margins');
    imagefill($mark, 0, 0, 0xffffff);
    imagepng($mark, $root . $markPath);
    $check($thumb($source, $markOptions, false) !== $marked && is_file($path($marked)), 'Replacement watermark creates a new cache identity');

    $_SERVER['HTTP_ACCEPT'] = 'image/webp;q=0, image/png';
    $check($thumb($source, 'w=40,h=40') === $base, 'WebP quality zero serves the base format');
    $_SERVER['HTTP_ACCEPT'] = 'image/webp, image/png';
    $check($thumb($source, 'w=40,h=40', false) === $base, 'Third argument disables automatic WebP');
    $auto = $thumb($source, 'w=40,h=40');
    $check($can('webp') ? $info($auto) === [40, 20, IMAGETYPE_WEBP] : $auto === $base, 'Automatic WebP serves the base result when unavailable');
    $explicit = $thumb($source, 'w=40,f=webp', false);
    $check($can('webp') ? $info($explicit) === [40, 20, IMAGETYPE_WEBP] : $explicit === $encodedSource, 'Explicit WebP is independent of the third argument');
    imagegif($mark, $root . 'assets/images/mark.gif');
    $check($info($thumb('assets/images/mark.gif', 'w=2')) === [2, 2, IMAGETYPE_GIF], 'GIF bypasses automatic WebP');
    foreach (['bmp' => IMAGETYPE_BMP, 'ico' => IMAGETYPE_ICO, 'wbmp' => IMAGETYPE_WBMP] as $format => $type) {
        $result = $thumb($source, "w=16,h=16,iar=1,f=$format", false);
        $check($can($format) ? $info($result) === [16, 16, $type] : $result === $encodedSource, "$format produces verified bytes or falls back");
    }

    $noise = imagecreatetruecolor(64, 64);
    for ($y = 0; $y < 64; $y++) {
        for ($x = 0; $x < 64; $x++) {
            imagesetpixel($noise, $x, $y, (($x * 701 + $y * 9719) * 211) & 0xffffff);
        }
    }
    imagepng($noise, $root . 'assets/images/noise.png');
    $full = $thumb('assets/images/noise.png', 'f=jpg,q=95', false);
    $target = intdiv(filesize($path($full)) * 3, 4);
    $limited = $thumb('assets/images/noise.png', "f=jpg,q=95,maxb=$target", false);
    $smallest = $thumb('assets/images/noise.png', 'f=jpg,q=95,maxb=1', false);
    $check(filesize($path($limited)) <= $target && filesize($path($smallest)) <= filesize($path($limited)) &&
        $info($smallest) === [64, 64, IMAGETYPE_JPEG], 'Byte target tries lower quality and keeps the smallest valid candidate when unreachable');

    foreach (['png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF] as $format => $type) {
        $full = $thumb('assets/images/noise.png', "f=$format", false);
        $target = intdiv(filesize($path($full)) * 3, 4);
        $limited = $thumb('assets/images/noise.png', "f=$format,maxb=$target", false);
        $check(filesize($path($limited)) <= $target && $info($limited) === [64, 64, $type],
            "Byte target reduces the $format palette");
    }

    imagejpeg($trimmed, $root . 'assets/images/camera.jpg');
    ob_start();
    imagejpeg($mark);
    $embedded = ob_get_clean();
    $jpeg = file_get_contents($root . 'assets/images/camera.jpg');
    $exif = "Exif\0\0II" . pack('vVvVv', 42, 8, 0, 14, 3) . pack('vvVV', 0x0103, 3, 1, 6)
        . pack('vvVV', 0x0201, 4, 1, 56) . pack('vvVVV', 0x0202, 4, 1, strlen($embedded), 0) . $embedded;
    file_put_contents($root . 'assets/images/camera.jpg', substr($jpeg, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2)
        . $exif . substr($jpeg, 2));
    $camera = $thumb('assets/images/camera.jpg', 'xto=1,w=2,f=png', false);
    $check(function_exists('exif_thumbnail') ? $info($camera) === [4, 4, IMAGETYPE_PNG] : $camera === 'assets/images/camera.jpg',
        'EXIF-only request extracts the embedded thumbnail without resizing');

    $check($info($thumb('assets/images/missing.png', 'w=4', false)) === [4, 4, IMAGETYPE_PNG], 'Missing source uses the site placeholder');
    $opts = PhpThumbOptions::parse('f=png,w=11,h=12,far=BR');
    $builder = (new sGalleryBuilder())->file($source)->thumbnail($opts, 'png', 'png', 75);
    $descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
    $job = json_decode(json_encode($descriptor->invoke($builder, 'png'), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $replayed = (new sGalleryBuilder())->processOptimization($job);
    $check($replayed === EVO_SITE_URL . $thumb($source, 'f=png,w=11,h=12,far=BR', false), 'Queued thumbnail replays the same cache key');
    $make($source, 48, 96);
    $check($thumb($source, 'w=40,h=40', false) !== $base && is_file($path($base)), 'Source replacement publishes under a new key');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}

