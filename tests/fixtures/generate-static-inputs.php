<?php
declare(strict_types=1);

// Explicit fixture maintenance command; never run by the runtime test suite.
$directory = __DIR__ . '/static-inputs/';
if (!is_dir($directory)) mkdir($directory, 0755);
$source = new Imagick(__DIR__ . '/heif-rgba-source.png');
foreach (['gif', 'webp', 'avif', 'bmp', 'tiff'] as $format) {
    if ($format === 'avif') {
        imageavif(imagecreatefrompng(__DIR__ . '/heif-rgba-source.png'), $directory . 'alpha.avif', 100);
        continue;
    }
    $frame = clone $source;
    $frame->setImageFormat($format);
    $frame->setImageCompressionQuality(100);
    if ($format === 'webp') $frame->setOption('webp:lossless', 'true');
    $frame->writeImage($directory . 'alpha.' . $format);
}
$frame = imagecreatetruecolor(32, 16);
imagefill($frame, 0, 0, imagecolorallocate($frame, 255, 255, 255));
imagecopy($frame, imagecreatefrompng(__DIR__ . '/heif-rgba-source.png'), 0, 0, 0, 0, 32, 16);
imagebmp($frame, $directory . 'opaque.bmp');
$frame = clone $source;
$frame->setImageFormat('TIFF');
$frame->setImageOrientation(6);
$frame->writeImage($directory . 'oriented.tiff');
$pages = new Imagick();
$pages->addImage($frame);
$pages->addImage($frame);
$pages->writeImages($directory . 'pages.tiff', true);
foreach (['webp', 'avif', 'tiff', 'gif', 'bmp'] as $format) {
    $frame = new Imagick();
    $frame->newImage(32, 16, new ImagickPixel('rgb(180,90,70)'), $format);
    $frame->setImageProfile('icc', file_get_contents(__DIR__ . '/DisplayP3-v4.icc'));
    $frame->setImageCompressionQuality(100);
    if ($format === 'webp') $frame->setOption('webp:lossless', 'true');
    if ($format === 'avif') {
        $frame->setImageFormat('PNG');
        $temporary = tempnam(sys_get_temp_dir(), 'sgallery-p3-');
        try {
            $frame->writeImage($temporary);
            $process = proc_open(['avifenc', '--lossless', '--icc', __DIR__ . '/DisplayP3-v4.icc', $temporary, $directory . 'p3.avif'], [STDIN, STDOUT, STDERR], $pipes);
            if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('avifenc fixture generation failed.');
        } finally {
            unlink($temporary);
        }
    } else {
        $frame->writeImage($directory . 'p3.' . $format);
    }
}
