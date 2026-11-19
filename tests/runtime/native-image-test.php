<?php
declare(strict_types=1);

use Seiger\sGallery\Services\NativeImage;

require __DIR__ . '/../../src/Services/GalleryFiles.php';
require __DIR__ . '/../../src/Services/ImageMemoryBudget.php';
require __DIR__ . '/../../src/Services/NativeImage.php';

$root = sys_get_temp_dir() . '/sgallery-native-image-' . bin2hex(random_bytes(8));
mkdir($root . '/cache', 0700, true);
define('EVO_BASE_PATH', $root . '/');

$check = static function (bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo "PASS $label\n";
};
$rejects = static function (callable $operation, string $message) use ($check): void {
    try {
        $operation();
    } catch (RuntimeException|InvalidArgumentException $error) {
        $check($error->getMessage() === $message, 'Rejected with: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $message);
};

try {
    // A synthetic 96 × 48 PNG with a transparent corner and an opaque center.
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAGAAAAAwCAYAAADuFn/PAAAAZElEQVR4nO3RsQ3AIADAMM7pYfy/MbcXMLGgxpFygceQJEmSJN3dms/r/QAAtAcAoD0AAO0BAGgPAEB7AADaAwDQHgCA9gAAtAcAoD0AAO0BAGgPAEB7AADaA/g7gCRJkiTprA+lqRk8WsX9mAAAAABJRU5ErkJggg==',
        true
    );
    $source = 'source[1].jpg';
    file_put_contents(EVO_BASE_PATH . $source, $png);
    file_put_contents(EVO_BASE_PATH . 'invalid.png', 'not an image');
    $rejects(fn () => NativeImage::open('invalid.png', 'webp'), 'Invalid or unsupported gallery image source.');
    $rejects(fn () => NativeImage::open($source, 'svg'), 'Unsupported native image format.');

    $gd = function_exists('gd_info') ? gd_info() : [];
    $imagick = class_exists(Imagick::class);

    // A synthetic 32 × 16 HEVC image, encoded with libheif and distinct red and blue halves.
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
        . '6PjEMyLDLxw+ZfQ6+eA=',
        true,
    );
    $sourceFixtures = [
        '24-bit BMP' => ['BMP', 2, 2, base64_decode(
            'Qk1GAAAAAAAAADYAAAAoAAAAAgAAAAIAAAABABgAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAD//wAAAAAAAP//AAAAAA==',
            true,
        )],
        'little-endian TIFF' => ['TIFF', 4, 2, base64_decode(
            'SUkqAAgAAAAJAAABBAABAAAABAAAAAEBBAABAAAAAgAAAAIBAwABAAAACAAAAAMBAwABAAA'
            . 'AAQAAAAYBAwABAAAAAQAAABEBBAABAAAAegAAABUBAwABAAAAAQAAABYBBAABAAAAAgAAABcBBAABAAAACAAAAAAAAAAAVar/AFWq/w==',
            true,
        )],
        'big-endian TIFF' => ['TIFF', 4, 2, base64_decode(
            'TU0AKgAAAAgACQEAAAQAAAABAAAABAEBAAQAAAABAAAAAgECAAMAAAABAAgAAAEDAAMAAA'
            . 'ABAAEAAAEGAAMAAAABAAEAAAERAAQAAAABAAAAegEVAAMAAAABAAEAAAEWAAQAAAABAAAAAgEXAAQAAAABAAAACAAAAAAAVar/AFWq/w==',
            true,
        )],
        'HEIC' => ['HEIC', 32, 16, $heic],
        // Keep the still-image payload and use generic HEIF's compatible major brand.
        'HEIF' => ['HEIC', 32, 16, substr_replace($heic, 'mif1', 8, 4)],
    ];
    $imagickCanRead = static function (string $codec, string $bytes) use ($imagick): bool {
        if (!$imagick || Imagick::queryFormats($codec) === []) {
            return false;
        }
        try {
            $probe = new Imagick();
            return $probe->readImageBlob($bytes);
        } catch (ImagickException) {
            return false;
        }
    };

    foreach ($sourceFixtures as $label => [$codec, $width, $height, $bytes]) {
        if (!is_string($bytes)) {
            throw new RuntimeException("Invalid $label test fixture.");
        }
        $input = "source[$label].bin";
        file_put_contents(EVO_BASE_PATH . $input, $bytes);
        if ($codec === 'HEIC') {
            if (!class_exists(finfo::class)) {
                echo "SKIP $label content identification without Fileinfo\n";
                continue;
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file(EVO_BASE_PATH . $input);
            $check($mime === 'image/' . strtolower($label), "$label is identified by its content");
        }
        $canRead = $imagickCanRead($codec, $bytes);
        if (!$canRead && !($codec === 'BMP' && function_exists('imagecreatefrombmp') && !empty($gd['PNG Support']))) {
            $rejects(fn () => NativeImage::open($input, 'png'), 'No native image driver could read the source and encode the requested format.');
            echo "SKIP $label conversion without a decoder\n";
            continue;
        }
        $decoded = NativeImage::open($input, 'png');
        $frame = $decoded->frame();
        $check($canRead ? $frame instanceof Imagick : $frame instanceof GdImage, "$label uses the available image driver");
        if ($codec === 'HEIC') {
            $left = $frame->getImagePixelColor(4, 8)->getColor();
            $right = $frame->getImagePixelColor(28, 8)->getColor();
            $check($left['r'] > $left['b'] && $right['b'] > $right['r'], "$label preserves the image's color regions");
        }
        $destination = 'cache/' . $label . '.png';
        $decoded->save($destination);
        $metadata = getimagesize(EVO_BASE_PATH . $destination);
        $check(array_slice($metadata, 0, 3) === [$width, $height, IMAGETYPE_PNG], "$label produces a PNG with the expected dimensions");
        $check(file_get_contents(EVO_BASE_PATH . $input) === $bytes, "$label source bytes are preserved");
    }
    file_put_contents(EVO_BASE_PATH . 'invalid.heic', 'not an image');
    $rejects(fn () => NativeImage::open('invalid.heic', 'png'), 'Invalid or unsupported gallery image source.');
    foreach (['heic', 'heif', 'tiff', 'bmp'] as $format) {
        $rejects(fn () => NativeImage::open($source, $format), 'Unsupported native image format.');
    }
    if ($imagickCanRead('TIFF', $sourceFixtures['little-endian TIFF'][3])) {
        $pages = new Imagick();
        $pages->newImage(4, 2, 'red', 'TIFF');
        $pages->newImage(4, 2, 'blue', 'TIFF');
        $pages->writeImages(EVO_BASE_PATH . 'pages.tiff', true);
        $check($pages->getNumberImages() === 2, 'The TIFF fixture has two pages');
        $rejects(fn () => NativeImage::open('pages.tiff', 'png'), 'No native image driver could read the source and encode the requested format.');
    }

    if (!$gd && !$imagick) {
        $rejects(fn () => NativeImage::open($source, 'png'), 'No native image driver could read the source and encode the requested format.');
        return;
    }

    $image = NativeImage::open($source, 'PNG');
    $image->save('cache/copy.png');
    $check(getimagesize(EVO_BASE_PATH . 'cache/copy.png')[2] === IMAGETYPE_PNG, 'Source format comes from bytes, even with brackets and a JPEG suffix');
    $check(file_get_contents(EVO_BASE_PATH . $source) === $png, 'The source is preserved');
    $rejects(fn () => $image->save('cache/copy.webp'), 'Invalid gallery image destination or quality.');
    $rejects(fn () => $image->save('cache/copy.png', 101), 'Invalid gallery image destination or quality.');
    $rejects(fn () => $image->save('missing/copy.png'), 'Gallery derivative directory is unavailable.');

    if (!empty($gd['PNG Support'])) {
        $wide = imagecreatetruecolor(8201, 4);
        imagepng($wide, EVO_BASE_PATH . 'wide.png');
        unset($wide);
        NativeImage::open('wide.png', 'png')->save('cache/wide.png');
        $check(array_slice(getimagesize(EVO_BASE_PATH . 'cache/wide.png'), 0, 2) === [8201, 4],
            'The native driver can round-trip a low-cost image above the old side cap');
    }

    $formats = [
        'jpg' => [IMAGETYPE_JPEG, 'JPEG Support'],
        'gif' => [IMAGETYPE_GIF, 'GIF Create Support'],
        'webp' => [IMAGETYPE_WEBP, 'WebP Support'],
        'avif' => [IMAGETYPE_AVIF, 'AVIF Support'],
    ];
    foreach ($formats as $extension => [$type, $gdCapability]) {
        $format = $extension === 'jpg' ? 'JPEG' : strtoupper($extension);
        $imagickCanEncode = false;
        if ($imagick && Imagick::queryFormats($format) !== []) {
            try {
                $probe = new Imagick();
                $probe->newImage(2, 2, 'black', $format);
                $imagickCanEncode = (@getimagesizefromstring($probe->getImageBlob())[2] ?? null) === $type;
            } catch (ImagickException) {
                // The loaded build can list an available decoder without an encoder.
            }
        }
        if (empty($gd[$gdCapability]) && !$imagickCanEncode) {
            $rejects(fn () => NativeImage::open($source, $extension), 'No native image driver could read the source and encode the requested format.');
            echo "SKIP round trip for $extension without a native encoder\n";
            continue;
        }

        $encoded = NativeImage::open($source, $extension);
        $driver = $encoded->frame() instanceof Imagick ? 'Imagick' : 'GD';
        $check($driver === ($imagickCanEncode ? 'Imagick' : 'GD'), "Selected $driver for PNG → $format");
        $destination = 'cache/frame.' . $extension;
        $encoded->save($destination);
        $metadata = getimagesize(EVO_BASE_PATH . $destination);
        $check([$metadata[0], $metadata[1], $metadata[2]] === [96, 48, $type], "$format output matches the requested type and dimensions");

        $decoded = NativeImage::open($destination, 'png')->frame();
        if ($extension !== 'webp') {
            continue;
        }
        if ($decoded instanceof GdImage) {
            $corner = imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0));
            $center = imagecolorsforindex($decoded, imagecolorat($decoded, 48, 24));
            $check($corner['alpha'] > 120 && $center['alpha'] < 3, 'GD decodes the preserved WebP transparency');
        } else {
            $corner = $decoded->getImagePixelColor(0, 0)->getColorValue(Imagick::COLOR_ALPHA);
            $center = $decoded->getImagePixelColor(48, 24)->getColorValue(Imagick::COLOR_ALPHA);
            $check($corner < 0.02 && $center > 0.98, 'Imagick decodes the preserved WebP transparency');
        }
    }

    if ($imagick) {
        /** Stand in for an encoder that reports success while delivering different bytes. @since 1.9.0 */
        $badEncoder = new class extends Imagick {
            public string $bytes;

            /** Write the supplied fixture bytes instead of the requested image. @since 1.9.0 */
            public function writeImageFile($filehandle, ?string $format = null): bool
            {
                return fwrite($filehandle, $this->bytes) === strlen($this->bytes);
            }
        };
        $target = 'cache/existing.jpg';
        file_put_contents(EVO_BASE_PATH . $target, 'previous contents');
        $class = new ReflectionClass(NativeImage::class);
        $frame = $class->newInstanceWithoutConstructor();
        $badEncoder->newImage(96, 48, 'black', 'png');
        $class->getConstructor()->invoke($frame, $badEncoder, IMAGETYPE_JPEG, EVO_BASE_PATH . $source);
        foreach (['', $png] as $bytes) {
            $badEncoder->bytes = $bytes;
            $rejects(fn () => $frame->save($target), 'The native encoder did not produce the expected image format and dimensions.');
            $check(file_get_contents(EVO_BASE_PATH . $target) === 'previous contents', 'The previous destination survives a false encoder success');
        }
        $pngTarget = 'cache/existing.png';
        file_put_contents(EVO_BASE_PATH . $pngTarget, $png);
        // The PNG signature and IHDR are intact. The pixel data is missing.
        $badEncoder->bytes = substr($png, 0, 33);
        $metadata = getimagesizefromstring($badEncoder->bytes);
        $check($metadata !== false && array_slice($metadata, 0, 3) === [96, 48, IMAGETYPE_PNG],
            'The incomplete PNG has the expected format and dimensions');
        $pngFrame = $class->newInstanceWithoutConstructor();
        $class->getConstructor()->invoke($pngFrame, $badEncoder, IMAGETYPE_PNG, EVO_BASE_PATH . $source);
        foreach ([false, true] as $optimize) {
            $pngFrame->optimize($optimize);
            $rejects(fn () => $pngFrame->save($pngTarget), 'The native encoder produced an image that could not be decoded.');
            $check(file_get_contents(EVO_BASE_PATH . $pngTarget) === $png,
                'An incomplete PNG leaves the previous valid cache intact with optimization ' . ($optimize ? 'on' : 'off'));
        }

        if (is_file(EVO_BASE_PATH . 'cache/frame.jpg')) {
            $badEncoder->resizeImage(24, 12, Imagick::FILTER_POINT, 1);
            $badEncoder->bytes = file_get_contents(EVO_BASE_PATH . 'cache/frame.jpg');
            $rejects(fn () => $frame->save($target), 'The native encoder did not produce the expected image format and dimensions.');
            $check(file_get_contents(EVO_BASE_PATH . $target) === 'previous contents', 'Correct format with incorrect dimensions is not published');
        }
        $check(glob(EVO_BASE_PATH . 'cache/.sgallery-image-*') === [], 'Failed writes clean up the temporary files');
    }
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
