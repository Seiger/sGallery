<?php
declare(strict_types=1);

use Seiger\sGallery\Services\NativeImage;

require __DIR__ . '/bootstrap-site.php';
$directory = 'assets/acceptance-inputs/';
if (!is_dir(EVO_BASE_PATH . $directory)) mkdir(EVO_BASE_PATH . $directory, 0755, true);
copy(__DIR__ . '/../fixtures/heif-rgba-source.png', EVO_BASE_PATH . $directory . 'source.png');
foreach (['jpeg', 'png', 'gif', 'webp', 'avif'] as $format) {
    $driver = NativeImage::canEncode($format, 'gd') ? 'gd' : 'imagick';
    NativeImage::open($directory . 'source.png', $format, $driver)->save($directory . 'input.' . $format, 90);
}
foreach (['BMP', 'TIFF'] as $format) {
    $frame = new Imagick(EVO_BASE_PATH . $directory . 'source.png');
    $frame->setImageFormat($format);
    $frame->writeImage(EVO_BASE_PATH . $directory . 'input.' . strtolower($format));
}
echo "Seeded fixed input files for decoder-only driver profiles\n";
