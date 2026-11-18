<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\ImageAnimation;
use Seiger\sGallery\Services\NativeImage;
use Spatie\Image\Image;

function config($key, $default = null) { return $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }

require __DIR__ . '/bootstrap.php';

$container = new Container();
Facade::setFacadeApplication($container);
$container->instance('log', new class {
    /** Surface a rendering failure in the fixture. @since 1.9.0 */
    public function error(string $message): void { throw new RuntimeException($message); }
});

$withSpatie = class_exists(Image::class);
$imagick = NativeImage::canEncode('gif', 'imagick') && NativeImage::canEncode('webp', 'imagick');
printf("PHP %s, Spatie %s, Imagick GIF and WebP %s\n", PHP_VERSION, $withSpatie ? 'on' : 'off', $imagick ? 'on' : 'off');

$root = sys_get_temp_dir() . '/sgallery-animation-' . bin2hex(random_bytes(6)) . '/';
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
    $builder = static fn (string $file): sGalleryBuilder =>
        (new sGalleryBuilder())->file($file)->quality(90)->optimize(false);
    $path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $fixtures = [
        'gif' => 'R0lGODlhDAAIAPAAAP8AAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQADwAAACwAAAAADAAIAAACCISPqcvtD2MqACH5BAAVAAAALAAAAAAMAAgAgAAA/wAAAAIIhI+py+0PYyoAOw==',
        'webp' => 'UklGRsAAAABXRUJQVlA4WAoAAAACAAAACwAABwAAQU5JTQYAAAD/////AABBTk1GSAAAAAAAAAAAAAsAAAcAAJYAAAJWUDggMAAAANABAJ0BKgwACAACADQloAJ0ugH4AAOwAP7wxAv/ILlhdcjX/yA/5Af8gP/48gAAAEFOTUZEAAAAAAAAAAAACwAABwAA0gAAAFZQOCAsAAAAlAEAnQEqDAAIAAAANCWgAnS6AAOYAP75k2//kB//kB//kB//ID/iF3sgMAA=',
    ];
    foreach ($fixtures as $format => $fixture) {
        $source = "basic.$format";
        $bytes = base64_decode($fixture, true);
        file_put_contents($root . $source, $bytes);
        $url = $builder($source)->fit('stretch', 6, 4)->format($format)->getFile();
        $check($builder($source)->fit('stretch', 6, 4)->format('jpeg')->getFile() === EVO_SITE_URL . $source,
            "$format animation serves the original for still formats");
        if (!$imagick) {
            $check($url === EVO_SITE_URL . $source, "$format animation stays intact without Imagick codecs");
            continue;
        }
        $type = $format === 'gif' ? IMAGETYPE_GIF : IMAGETYPE_WEBP;
        $output = $path($url);
        $check(array_slice(getimagesize($output), 0, 3) === [6, 4, $type], "$format basic animation is resized");
        $timing = ImageAnimation::timing($output, $type);
        $check($timing === ImageAnimation::timing($root . $source, $type), "$format keeps the basic animation timing and infinite loop");
        if ($withSpatie) {
            echo 'Spatie ' . $format . ' ' . json_encode($timing) . ' ' . hash_file('sha256', $output) . PHP_EOL;
        }
        $check(file_get_contents($root . $source) === $bytes, "$format original file is unchanged");
    }
    if ($withSpatie || !$imagick) {
        return;
    }

    $gif = new Imagick();
    foreach ([[32, 24, 0, 0, 'transparent'], [8, 8, 16, 8, 'blue'], [8, 8, 16, 8, 'blue'], [8, 8, 24, 16, 'lime']] as $i => [$w, $h, $x, $y, $color]) {
        $frame = new Imagick();
        $frame->newImage($w, $h, $color, 'GIF');
        if ($i === 0) {
            $draw = new ImagickDraw();
            $draw->setFillColor('red');
            $draw->rectangle(0, 0, 7, 7);
            $frame->drawImage($draw);
        }
        $frame->setImagePage(32, 24, $x, $y);
        $frame->setImageDelay([11, 24, 4, 20][$i]);
        $frame->setImageIterations(3);
        $frame->setImageDispose(Imagick::DISPOSE_NONE);
        $gif->addImage($frame);
    }
    $gif->setFirstIterator();
    $gif->writeImages($root . 'partial.gif', true);
    $expectedGif = ['loops' => 3, 'delays' => [110, 240, 40, 200]];
    $check(ImageAnimation::timing($root . 'partial.gif', IMAGETYPE_GIF) === $expectedGif, 'Partial GIF fixture has a finite loop and four delays');
    foreach (['gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP] as $format => $type) {
        $url = $builder('partial.gif')->fit('stretch', 16, 12)->format($format)->getFile();
        $file = $path($url);
        $check(array_slice(getimagesize($file), 0, 3) === [16, 12, $type] && ImageAnimation::timing($file, $type) === $expectedGif,
            "GIF to $format keeps the finite loop, delays and duplicate frames");
        $frames = (new Imagick($file))->coalesceImages();
        $frames->setIteratorIndex(1);
        $red = $frames->getImagePixelColor(2, 2)->getColor();
        $blue = $frames->getImagePixelColor(10, 6)->getColor();
        $alpha = $frames->getImagePixelColor(7, 10)->getColorValue(Imagick::COLOR_ALPHA);
        $check($frames->getNumberImages() === 4 && $red['r'] > 220 && $red['b'] < 40 && $blue['b'] > 220 && $alpha < 0.03,
            "GIF to $format composites offset frames and preserves transparency");
        if ($format === 'gif') {
            $check($builder('partial.gif')->fit('stretch', 16, 12)->getFile() === $url, 'Default native animation output keeps the source format');
        }
    }

    // Build a source independently of the package, using lossless still chunks.
    $chunk = static fn (string $type, string $bytes): string =>
        $type . pack('V', strlen($bytes)) . $bytes . (strlen($bytes) % 2 ? "\0" : '');
    $u24 = static fn (int $value): string => substr(pack('V', $value), 0, 3);
    $payloads = [];
    foreach (['red', 'blue', 'blue', 'lime'] as $color) {
        $frame = new Imagick();
        $frame->newImage(32, 24, $color, 'WEBP');
        $frame->setOption('webp:lossless', 'true');
        $blob = $frame->getImageBlob();
        $offset = strpos($blob, 'VP8L', 12);
        if ($offset === false) {
            throw new RuntimeException('Cannot build the lossless WebP fixture');
        }
        $payloads[] = substr($blob, $offset);
    }
    $writeWebp = static function (string $name, array $timing) use ($root, $chunk, $u24, $payloads): void {
        $size = $u24(31) . $u24(23);
        $container = 'WEBP' . $chunk('VP8X', "\x02\0\0\0" . $size) . $chunk('ANIM', "\0\0\0\0" . pack('v', $timing['loops']));
        foreach ($payloads as $i => $payload) {
            $container .= $chunk('ANMF', str_repeat("\0", 6) . $size . $u24($timing['delays'][$i]) . "\x02" . $payload);
        }
        file_put_contents($root . $name, 'RIFF' . pack('V', strlen($container)) . $container);
    };
    $expectedWebp = ['loops' => 4, 'delays' => [111, 237, 43, 201]];
    $writeWebp('uneven.webp', $expectedWebp);
    $render = static fn (): string => $builder('uneven.webp')->resize(16, 12)->format('webp')->getFile();
    $url = $render();
    $file = $path($url);
    $check(array_slice(getimagesize($file), 0, 3) === [16, 12, IMAGETYPE_WEBP] &&
        ImageAnimation::timing($file, IMAGETYPE_WEBP) === $expectedWebp, 'WebP resize retains duplicate frames and exact millisecond durations');
    $check($builder('uneven.webp')->format('gif')->getFile() === EVO_SITE_URL . 'uneven.webp',
        'GIF conversion serves the original when it cannot represent a WebP delay exactly');
    $singlePlay = ['loops' => 1, 'delays' => [110, 240, 40, 200]];
    $writeWebp('once.webp', $singlePlay);
    $gifPath = $path($builder('once.webp')->fit('stretch', 16, 12)->format('gif')->getFile());
    $check(array_slice(getimagesize($gifPath), 0, 3) === [16, 12, IMAGETYPE_GIF] &&
        ImageAnimation::timing($gifPath, IMAGETYPE_GIF) === $singlePlay, 'WebP to GIF keeps four frames and plays once');
    $gifFrames = (new Imagick($gifPath))->coalesceImages();
    foreach (['r', 'b', 'b', 'g'] as $i => $channel) {
        $gifFrames->setIteratorIndex($i);
        $check($gifFrames->getImagePixelColor(8, 6)->getColor()[$channel] > 220, "WebP to GIF preserves the color of frame $i");
    }
    $bytes = file_get_contents($file);
    $duration = strpos($bytes, 'ANMF') + 20;
    file_put_contents($file, substr_replace($bytes, $u24(999), $duration, 3));
    $check($render() === $url && ImageAnimation::timing($file, IMAGETYPE_WEBP) === $expectedWebp, 'An animation cache entry with different timing is regenerated');
    touch($file, 1700000000);
    clearstatcache(true, $file);
    $check($render() === $url && filemtime($file) === 1700000000, 'A verified animation cache entry is reused');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
