<?php
declare(strict_types=1);

if (extension_loaded('exif')) {
    throw new RuntimeException('Run this regression with the EXIF extension disabled');
}

require __DIR__ . '/runtime/thumbnail-test.php';

try {
    $source = $root . 'media/oriented.jpg';
    $image = imagecreatetruecolor(80, 40);
    imagejpeg($image, $source);
    unset($image);

    // A little-endian TIFF orientation of 6 requests a 90-degree camera rotation.
    $exif = "Exif\0\0II" . pack('vVv', 42, 8, 1)
        . pack('vvVVV', 0x0112, 3, 1, 6, 0);
    $jpeg = file_get_contents($source);
    file_put_contents($source, substr($jpeg, 0, 2) . "\xff\xe1"
        . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));

    $output = (string) (new Seiger\sGallery\Builders\sGalleryBuilder())
        ->file('media/oriented.jpg')->fit('max', 40, 40)->format('webp');
    $path = str_replace(EVO_SITE_URL, EVO_BASE_PATH, $output);
    ckReady($output !== EVO_SITE_URL . 'media/oriented.jpg' && is_file($path),
        'EXIF-tagged JPEG produces a derivative without EXIF');
    $size = getimagesize($path);
    ckReady($size[0] === 40 && $size[1] === 20 && $size['mime'] === 'image/webp',
        'GD preserves stored orientation and resizes without EXIF');
} finally {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
