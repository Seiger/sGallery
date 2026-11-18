<?php namespace Seiger\sGallery\Services;

use GdImage;
use Imagick;
use ImagickException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Read and encode a local raster image with a native PHP image driver.
 *
 * @internal
 * @since 1.9.0
 */
final class NativeImage
{
    public const FIT_MODES = ['crop', 'contain', 'max', 'fill', 'stretch', 'fill-max'];

    public const CROP_POSITIONS = [
        'topLeft' => [0, 0], 'top' => [0.5, 0], 'topRight' => [1, 0],
        'left' => [0, 0.5], 'center' => [0.5, 0.5], 'right' => [1, 0.5],
        'bottomLeft' => [0, 1], 'bottom' => [0.5, 1], 'bottomRight' => [1, 1],
    ];

    private const READ_WRITE_FORMATS = [
        IMAGETYPE_JPEG => ['JPEG', 'imagecreatefromjpeg', 'imagejpeg', 'JPEG Support'],
        IMAGETYPE_PNG => ['PNG', 'imagecreatefrompng', 'imagepng', 'PNG Support'],
        IMAGETYPE_GIF => ['GIF', 'imagecreatefromgif', 'imagegif', 'GIF Read Support'],
        IMAGETYPE_WEBP => ['WEBP', 'imagecreatefromwebp', 'imagewebp', 'WebP Support'],
        IMAGETYPE_AVIF => ['AVIF', 'imagecreatefromavif', 'imageavif', 'AVIF Support'],
    ];

    private const READ_ONLY_FORMATS = [
        IMAGETYPE_BMP => ['BMP', 'imagecreatefrombmp'],
        IMAGETYPE_TIFF_II => ['TIFF', null],
        IMAGETYPE_TIFF_MM => ['TIFF', null],
    ];

    private static array $imagickEncoders = [];

    /** Hold the decoded frame and the output type chosen for this operation. @since 1.9.0 */
    private function __construct(
        private readonly GdImage|Imagick $image,
        private readonly int $outputType,
        private readonly string $source,
    ) {
    }

    /**
     * Open a site-relative raster source using a driver that can also encode the requested type.
     *
     * The codecs operate on still images. Inspect the file data for the input type and
     * let Imagick probe dimensions when PHP cannot read a HEIC/HEIF header.
     *
     * @param 'gd'|'imagick'|null $driver A specific driver when a caller must preserve its processing behavior.
     * @throws InvalidArgumentException If the requested output format is unknown.
     * @throws RuntimeException If the source cannot be read with a suitable driver.
     * @since 1.9.0
     */
    public static function open(string $source, string $format, ?string $driver = null): self
    {
        $outputType = self::type($format);
        $path = GalleryFiles::local($source);
        if ($path === null) {
            throw new RuntimeException('Invalid or unsupported gallery image source.');
        }

        $metadata = @getimagesize($path);
        $input = $metadata === false ? null
            : (self::READ_WRITE_FORMATS[$metadata[2]] ?? self::READ_ONLY_FORMATS[$metadata[2]] ?? null);
        if ($input === null && self::isHeif($path)) {
            $input = ['HEIC', null];
        }
        if ($input === null || ($metadata !== false && ($metadata[0] <= 0 || $metadata[1] <= 0))) {
            throw new RuntimeException('Invalid or unsupported gallery image source.');
        }
        $fileBytes = (int) @filesize($path);
        $hasMemory = $metadata === false
            ? ImageMemoryBudget::allows(0, $fileBytes, PHP_SAPI === 'cli')
            : self::hasMemoryFor($metadata[0], $metadata[1], $fileBytes);
        if (!$hasMemory) {
            throw new RuntimeException('Insufficient memory budget to decode the gallery image.');
        }

        [$inputFormat, $gdDecoder] = $input;
        if ($driver !== 'gd' && self::canEncodeWithImagick($outputType) && Imagick::queryFormats($inputFormat) !== []) {
            $image = self::readWithImagick($path, $inputFormat);
            if ($image instanceof Imagick) {
                return new self($image, $outputType, $path);
            }
        }

        if ($driver !== 'imagick' && $gdDecoder !== null && function_exists($gdDecoder) && function_exists('gd_info')) {
            $capabilities = gd_info();
            $inputCapability = $input[3] ?? null;
            if (($inputCapability === null || !empty($capabilities[$inputCapability]))
                && self::canEncodeWithGd($outputType, $capabilities)) {
                $image = @$gdDecoder($path);
                if ($image instanceof GdImage && imagesx($image) === $metadata[0] && imagesy($image) === $metadata[1]) {
                    return new self($image, $outputType, $path);
                }
            }
        }

        throw new RuntimeException('No native image driver could read the source and encode the requested format.');
    }

    /** Check whether the selected backend can write and verify the requested output. @since 1.9.0 */
    public static function canEncode(string $format, string $driver): bool
    {
        $type = self::type($format);
        return $driver === 'imagick'
            ? self::canEncodeWithImagick($type)
            : $driver === 'gd' && function_exists('gd_info') && self::canEncodeWithGd($type, gd_info());
    }

    /** Check GD's format-specific reader and writer, including its separate GIF flags. @since 1.9.0 */
    private static function canEncodeWithGd(int $type, array $capabilities): bool
    {
        [, $decoder, $encoder, $capability] = self::READ_WRITE_FORMATS[$type];
        $outputCapability = $type === IMAGETYPE_GIF ? 'GIF Create Support' : $capability;
        return !empty($capabilities[$outputCapability]) && function_exists($decoder) && function_exists($encoder);
    }

    /** Encode a frame already transformed by the Builder while protecting the local source. @since 1.9.0 */
    public static function fromFrame(GdImage|Imagick $frame, string $source, string $format): self
    {
        $path = GalleryFiles::local($source);
        if ($path === null) {
            throw new RuntimeException('Invalid or unsupported gallery image source.');
        }

        return new self($frame, self::type($format), $path);
    }

    /** Identify still HEIC/HEIF from local file contents before selecting the Imagick coder. @since 1.9.0 */
    private static function isHeif(string $path): bool
    {
        return class_exists(\finfo::class)
            && in_array((new \finfo(FILEINFO_MIME_TYPE))->file($path), ['image/heic', 'image/heif'], true);
    }

    /** Expose the decoded frame to callers that apply driver-specific transformations. @since 1.9.0 */
    public function frame(): GdImage|Imagick
    {
        return $this->image;
    }

    /** Read the current frame dimensions for WebP quality selection. @since 1.9.0 */
    public function dimensions(): array
    {
        return self::dimensionsOf($this->image);
    }

    /** Detect PNG alpha or color-key transparency without loading the pixel data. @since 1.9.0 */
    public static function pngMayHaveTransparency(string $path): bool
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            // The PNG signature and IHDR occupy the first 33 bytes. The color type is at byte 25.
            $header = (string) fread($stream, 33);
            if (!str_starts_with($header, "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR")) {
                return false;
            }
            if (strlen($header) < 33 || in_array(ord($header[25]), [4, 6], true)) {
                return true;
            }

            while (strlen($header = (string) fread($stream, 8)) === 8) {
                $type = substr($header, 4);
                if ($type === 'tRNS') {
                    return true;
                }
                if ($type === 'IDAT' || $type === 'IEND') {
                    return false;
                }
                $length = unpack('N', $header)[1];
                // Skip the chunk data and CRC. Transparency must precede IDAT.
                if ($length > 0x7fffffff || fseek($stream, $length + 4, SEEK_CUR) !== 0) {
                    break;
                }
            }

            return true;
        } finally {
            fclose($stream);
        }
    }

    /** Convert GD palette and color-key transparency to per-pixel alpha before resampling. @since 1.9.0 */
    public function withPixelAlpha(): self
    {
        if (!$this->image instanceof GdImage || (imageistruecolor($this->image) && imagecolortransparent($this->image) === -1)) {
            return $this;
        }

        [$width, $height] = $this->dimensions();
        return $this->manualCrop(0, 0, $width, $height);
    }

    /** Composite the finished frame on the default white background for a native JPEG. @since 1.9.0 */
    public function withWhiteJpegBackground(): self
    {
        if ($this->outputType !== IMAGETYPE_JPEG) {
            return $this;
        }

        [$width, $height] = $this->dimensions();
        if (!self::hasMemoryFor($width, $height)) {
            throw new RuntimeException('Insufficient memory budget to composite the gallery image.');
        }

        if ($this->image instanceof Imagick) {
            $frame = clone $this->image;
            $success = $frame->setImageBackgroundColor('white') && $frame->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        } else {
            $frame = self::newGdFrame($width, $height);
            imagealphablending($frame, true);
            $success = imagefill($frame, 0, 0, 0xffffff) && imagecopy($frame, $this->image, 0, 0, 0, 0, $width, $height);
        }
        if (!$success) {
            throw new RuntimeException('The native image driver could not composite the gallery image on white.');
        }

        return new self($frame, $this->outputType, $this->source);
    }

    /** Read the orientation of a validated local JPEG without decoding its pixels. @since 1.9.0 */
    public static function readJpegOrientation(string $path): int
    {
        if (function_exists('exif_read_data')) {
            $orientation = @exif_read_data($path, 'IFD0')['Orientation'] ?? 1;
        } elseif (class_exists(Imagick::class) && ($stream = @fopen($path, 'rb')) !== false) {
            try {
                $orientation = self::pingImagick($stream, 'JPEG')?->getImageOrientation() ?? 1;
            } catch (ImagickException) {
                return 1;
            } finally {
                fclose($stream);
            }
        } else {
            return 1;
        }

        return is_int($orientation) && $orientation >= 2 && $orientation <= 8 ? $orientation : 1;
    }

    /** Rotate or mirror a JPEG frame before applying geometry in displayed coordinates. @since 1.9.0 */
    public function orientFromExif(int $orientation): self
    {
        if ($orientation < 2 || $orientation > 8) {
            return $this;
        }

        [$width, $height] = $this->dimensions();
        if (!self::hasMemoryFor($width, $height)) {
            throw new RuntimeException('Insufficient memory budget to orient the gallery image.');
        }

        if ($this->image instanceof Imagick) {
            $image = clone $this->image;
            if (!$image->setImageOrientation($orientation)) {
                throw new RuntimeException('Imagick could not set the gallery image orientation.');
            }
            $image->autoOrient();
            if (!$image->setImagePage(0, 0, 0, 0)) {
                throw new RuntimeException('Imagick could not reset the oriented gallery image canvas.');
            }
            // Do not carry the source orientation into a derivative with rotated pixels.
            if ($image->getImageProfiles('exif', false) !== []) {
                $image->removeImageProfile('exif');
            }
        } else {
            $rotation = match ($orientation) {
                3 => 180,
                5, 6, 7 => -90,
                8 => 90,
                default => 0,
            };
            $flip = match ($orientation) {
                2, 5 => IMG_FLIP_HORIZONTAL,
                4, 7 => IMG_FLIP_VERTICAL,
                default => null,
            };
            $image = $rotation === 0
                ? self::cropFrame($this->image, 0, 0, $width, $height)
                : imagerotate($this->image, $rotation, 0);
            if ($image === false || ($flip !== null && !imageflip($image, $flip))) {
                throw new RuntimeException('GD could not orient the gallery image.');
            }
        }

        return new self($image, $this->outputType, $this->source);
    }

    /**
     * Resize a still frame using Builder's width-then-height behavior and rounding.
     *
     * Each pass preserves aspect ratio and transparency. Return a new frame so the
     * original can be reused by another driver if the transformation fails.
     *
     * @since 1.9.0
     */
    public function resize(int $width, int $height): self
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Gallery resize dimensions must be positive.');
        }

        $image = $this->image instanceof Imagick ? clone $this->image : $this->image;
        [$sourceWidth, $sourceHeight] = self::dimensionsOf($image);
        $image = self::scaleFrame($image, $width, max(1, round($width * ($sourceHeight / $sourceWidth))));
        [$currentWidth, $currentHeight] = self::dimensionsOf($image);
        $aspect = $currentWidth / $currentHeight;
        $widthAtHeight = max(1, round($height * $aspect));
        // Match the legacy aspect-ratio constraint when rounding the second pass.
        $heightAtWidth = max(1, round($widthAtHeight / $aspect));
        $widthAfterRounding = max(1, round($height * ($widthAtHeight / $heightAtWidth)));
        $image = self::scaleFrame($image, min($widthAfterRounding, $widthAtHeight), $height);

        return new self($image, $this->outputType, $this->source);
    }

    /** Crop a pixel rectangle at one of the nine positions in the source frame. @since 1.9.0 */
    public function crop(string $position, int $width, int $height): self
    {
        [$sourceWidth, $sourceHeight] = $this->dimensions();
        [$anchorX, $anchorY] = self::CROP_POSITIONS[$position] ?? self::CROP_POSITIONS['center'];
        $x = (int) (max(0, $sourceWidth - $width) * $anchorX);
        $y = (int) (max(0, $sourceHeight - $height) * $anchorY);

        return $this->manualCrop($x, $y, $width, $height);
    }

    /** Center a crop on a source pixel and shift it inside the source edges. @since 1.9.0 */
    public function focalCrop(int $centerX, int $centerY, int $width, int $height): self
    {
        [$sourceWidth, $sourceHeight] = $this->dimensions();
        $width = min($width, $sourceWidth);
        $height = min($height, $sourceHeight);
        $x = (int) max(0, min($sourceWidth - $width, $centerX - $width / 2));
        $y = (int) max(0, min($sourceHeight - $height, $centerY - $height / 2));

        return $this->manualCrop($x, $y, $width, $height);
    }

    /** Crop from the given source pixel, trimming the rectangle at the right and bottom edges. @since 1.9.0 */
    public function manualCrop(int $x, int $y, int $width, int $height): self
    {
        [$sourceWidth, $sourceHeight] = $this->dimensions();
        $width = min($width, $sourceWidth - $x);
        $height = min($height, $sourceHeight - $y);
        if ($x < 0 || $y < 0 || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('The gallery crop must start inside the source and have positive dimensions.');
        }
        if (!self::hasMemoryFor($width, $height)) {
            throw new RuntimeException('Insufficient memory budget to crop the gallery image.');
        }

        $image = $this->image instanceof Imagick ? clone $this->image : $this->image;
        return new self(self::cropFrame($image, $x, $y, $width, $height), $this->outputType, $this->source);
    }

    /**
     * Fit a still frame inside or over the requested rectangle.
     *
     * Fill modes center the image on white padding while retaining its alpha.
     * Crop centers a covering image. Stretch uses the exact requested dimensions.
     *
     * @since 1.9.0
     */
    public function fit(string $mode, int $width, int $height): self
    {
        if (!in_array($mode, self::FIT_MODES, true) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('Invalid gallery fit mode or dimensions.');
        }

        [$sourceWidth, $sourceHeight] = $this->dimensions();
        $aspect = $sourceWidth / $sourceHeight;
        [$scaledWidth, $scaledHeight] = match ($mode) {
            'stretch' => [$width, $height],
            'crop' => $aspect > $width / $height
                ? [round($height * $aspect), $height]
                : [$width, round($width / $aspect)],
            default => self::fitInside($sourceWidth, $sourceHeight, $width, $height, !in_array($mode, ['fill', 'max'], true)),
        };
        $needsCanvas = in_array($mode, ['crop', 'fill', 'fill-max'], true)
            && ($scaledWidth != $width || $scaledHeight != $height);
        $canvasPixels = $needsCanvas ? (float) $width * $height : 0;
        if (!ImageMemoryBudget::allows((float) $scaledWidth * $scaledHeight + $canvasPixels, 0, PHP_SAPI === 'cli')) {
            throw new RuntimeException('Insufficient memory budget to fit the gallery image.');
        }

        $image = $this->image instanceof Imagick ? clone $this->image : $this->image;
        $image = self::scaleFrame($image, $scaledWidth, $scaledHeight);
        if ($needsCanvas) {
            $image = self::centerOnCanvas($image, $width, $height, $mode === 'crop');
        }

        return new self($image, $this->outputType, $this->source);
    }

    /** Fit within both bounds using the same integer rounding as the existing Builder. @since 1.9.0 */
    private static function fitInside(int $sourceWidth, int $sourceHeight, int $width, int $height, bool $upscale): array
    {
        [$scaledWidth, $scaledHeight] = self::scaleToSide($sourceWidth, $sourceHeight, $width, $upscale);
        [$scaledHeight, $scaledWidth] = self::scaleToSide($scaledHeight, $scaledWidth, $height, $upscale);
        if ($scaledWidth <= $width) {
            return [$scaledWidth, $scaledHeight];
        }

        [$scaledHeight, $scaledWidth] = self::scaleToSide($sourceHeight, $sourceWidth, $height, $upscale);
        return self::scaleToSide($scaledWidth, $scaledHeight, $width, $upscale);
    }

    /** Scale one side and round the other, optionally limiting both to their source lengths. @since 1.9.0 */
    private static function scaleToSide(int|float $side, int|float $otherSide, int $target, bool $upscale): array
    {
        $scaledSide = $upscale ? $target : min($target, $side);
        $scaledOtherSide = max(1, round($scaledSide / ($side / $otherSide)));

        return [$scaledSide, $upscale ? $scaledOtherSide : min($scaledOtherSide, $otherSide)];
    }

    /** Center a frame by cropping its excess or copying it onto white padding. @since 1.9.0 */
    private static function centerOnCanvas(GdImage|Imagick $image, int $width, int $height, bool $crop): GdImage|Imagick
    {
        [$imageWidth, $imageHeight] = self::dimensionsOf($image);
        $x = intdiv($width, 2) - intdiv($imageWidth, 2);
        $y = intdiv($height, 2) - intdiv($imageHeight, 2);

        if ($crop) {
            return self::cropFrame($image, -$x, -$y, $width, $height);
        }

        if ($image instanceof Imagick) {
            $canvas = new Imagick();
            if ($canvas->newImage($width, $height, 'white', 'PNG') &&
                $canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE) &&
                $canvas->compositeImage($image, Imagick::COMPOSITE_COPY, $x, $y)) {
                return $canvas;
            }
        } else {
            $canvas = self::newGdFrame($width, $height);
            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            if (imagefill($canvas, 0, 0, 0xffffff) &&
                imagefilledrectangle($canvas, $x, $y, $x + $imageWidth - 1, $y + $imageHeight - 1, $transparent) &&
                imagecopy($canvas, $image, $x, $y, 0, 0, $imageWidth, $imageHeight)) {
                return $canvas;
            }
        }

        throw new RuntimeException('The native image driver could not center the gallery image.');
    }

    /** Copy a rectangle without resampling or discarding transparent pixels. @since 1.9.0 */
    private static function cropFrame(GdImage|Imagick $image, int $x, int $y, int $width, int $height): GdImage|Imagick
    {
        if ($image instanceof Imagick) {
            if ($image->setImagePage(0, 0, 0, 0) && $image->cropImage($width, $height, $x, $y) &&
                $image->setImagePage(0, 0, 0, 0)) {
                return $image;
            }
        } else {
            $frame = self::newGdFrame($width, $height);
            $transparent = imagecolorallocatealpha($frame, 255, 255, 255, 127);
            if (imagefill($frame, 0, 0, $transparent) && imagecopy($frame, $image, 0, 0, $x, $y, $width, $height)) {
                return $frame;
            }
        }

        throw new RuntimeException('The native image driver could not crop the gallery image.');
    }

    /** Return the pixel dimensions for either raster driver. @since 1.9.0 */
    private static function dimensionsOf(GdImage|Imagick $image): array
    {
        return $image instanceof GdImage ? [imagesx($image), imagesy($image)] : [$image->getImageWidth(), $image->getImageHeight()];
    }

    /** Allocate a resampled frame only after checking available memory. @since 1.9.0 */
    private static function scaleFrame(GdImage|Imagick $image, int|float $width, int|float $height): GdImage|Imagick
    {
        if (!ImageMemoryBudget::allows((float) $width * $height, 0, PHP_SAPI === 'cli')) {
            throw new RuntimeException('Insufficient memory budget to resize the gallery image.');
        }
        $width = (int) $width;
        $height = (int) $height;
        [$sourceWidth, $sourceHeight] = self::dimensionsOf($image);
        if ($width === $sourceWidth && $height === $sourceHeight) {
            return $image;
        }

        if ($image instanceof Imagick) {
            if (!$image->scaleImage($width, $height)) {
                throw new RuntimeException('Imagick could not resize the gallery image.');
            }
            return $image;
        }

        $frame = self::newGdFrame($width, $height);
        if (!imagecopyresampled($frame, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
            throw new RuntimeException('GD could not resize the gallery image.');
        }
        return $frame;
    }

    /** Allocate a GD frame ready to receive pixels with alpha. @since 1.9.0 */
    private static function newGdFrame(int $width, int $height): GdImage
    {
        $frame = imagecreatetruecolor($width, $height);
        if ($frame === false) {
            throw new RuntimeException('GD could not allocate the gallery frame.');
        }
        imagealphablending($frame, false);
        imagesavealpha($frame, true);

        return $frame;
    }

    /** Save a candidate at the canonical absolute path selected by the Builder. @since 1.9.0 */
    public function saveToPath(string $path, int $quality, ?callable $optimize = null): void
    {
        $root = realpath(EVO_BASE_PATH);
        if ($root === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Invalid gallery derivative path.');
        }
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
        $this->save($relative, $quality, $optimize);
    }

    /**
     * Publish an encoded frame to a site-relative derivative path in an existing directory.
     *
     * The caller selects the derivative directory. The selected driver must decode the
     * staged frame before it replaces any existing derivative.
     *
     * @param (callable(string): void)|null $optimize Optional post-encoding optimization of the staged file.
     * @throws InvalidArgumentException If the path or quality is outside the supported input.
     * @throws RuntimeException If encoding, verification or publication fails.
     * @since 1.9.0
     */
    public function save(string $destination, int $quality = 85, ?callable $optimize = null): void
    {
        if (!GalleryFiles::relative($destination) || self::type(pathinfo($destination, PATHINFO_EXTENSION)) !== $this->outputType
            || $quality < 0 || $quality > 100) {
            throw new InvalidArgumentException('Invalid gallery image destination or quality.');
        }

        $parent = dirname($destination);
        $directory = $parent === '.' ? realpath(EVO_BASE_PATH) : GalleryFiles::directory($parent);
        if (!$directory) {
            throw new RuntimeException('Gallery derivative directory is unavailable.');
        }

        $path = $directory . DIRECTORY_SEPARATOR . basename($destination);
        if (is_link($path) || (file_exists($path) && !is_file($path)) || realpath($path) === $this->source) {
            throw new RuntimeException('Cannot publish a derivative at this gallery path.');
        }

        if ($this->image instanceof Imagick && $this->image->getNumberImages() !== 1) {
            throw new RuntimeException('The native image writer requires a single frame.');
        }
        [$width, $height] = $this->dimensions();
        if (!self::hasMemoryFor($width, $height)) {
            throw new RuntimeException('Insufficient memory budget to encode the gallery image.');
        }

        $temporary = @tempnam($directory, '.sgallery-image-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create a temporary gallery image.');
        }

        try {
            if (dirname($temporary) !== $directory) {
                throw new RuntimeException('Cannot stage a gallery image in the destination directory.');
            }
            $this->encode($temporary, $quality);
            if ($optimize !== null) {
                $optimize($temporary);
            }
            $metadata = @getimagesize($temporary);
            if (!$metadata || [$metadata[0], $metadata[1], $metadata[2]] !== [$width, $height, $this->outputType]) {
                throw new RuntimeException('The native encoder did not produce the expected image format and dimensions.');
            }
            if (!$this->decodesAsExpected($temporary, $width, $height)) {
                throw new RuntimeException('The native encoder produced an image that could not be decoded.');
            }
            if (!@chmod($temporary, 0644 & ~umask()) || !@rename($temporary, $path)) {
                throw new RuntimeException('Cannot publish the verified gallery image.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Read the staged pixels with the selected driver before replacing the derivative.
     * The memory check accounts for the frame still held by this instance.
     *
     * @since 1.9.0
     */
    private function decodesAsExpected(string $path, int $width, int $height): bool
    {
        [$format, $decoder] = self::READ_WRITE_FORMATS[$this->outputType];
        if ($this->image instanceof Imagick) {
            $decoded = self::readWithImagick($path, $format);
            return $decoded !== null && $decoded->getImageWidth() === $width && $decoded->getImageHeight() === $height;
        }

        if (!self::hasMemoryFor($width, $height, (int) @filesize($path))) {
            return false;
        }
        $decoded = @$decoder($path);
        return $decoded instanceof GdImage && imagesx($decoded) === $width && imagesy($decoded) === $height;
    }

    /** Encode in the selected format, independent of the temporary filename. @since 1.9.0 */
    private function encode(string $path, int $quality): void
    {
        [$format, , $encoder] = self::READ_WRITE_FORMATS[$this->outputType];
        if ($this->image instanceof GdImage) {
            if (in_array($this->outputType, [IMAGETYPE_WEBP, IMAGETYPE_AVIF], true)
                && !imageistruecolor($this->image) && !imagepalettetotruecolor($this->image)) {
                throw new RuntimeException('Cannot convert a palette frame for the native encoder.');
            }
            imagesavealpha($this->image, true);
            $success = match ($this->outputType) {
                IMAGETYPE_PNG => @$encoder($this->image, $path, min(9, (int) round((100 - $quality) / 10))),
                IMAGETYPE_GIF => @$encoder($this->image, $path),
                default => @$encoder($this->image, $path, $quality),
            };
        } else {
            $stream = @fopen($path, 'wb');
            if ($stream === false) {
                throw new RuntimeException('Cannot open the temporary gallery image.');
            }
            try {
                $success = $this->image->setImageFormat($format)
                    && $this->image->setImageCompressionQuality($quality)
                    && $this->image->setCompressionQuality($format === 'PNG' ? 100 - $quality : $quality)
                    && $this->image->writeImageFile($stream, $format)
                    && fflush($stream);
            } catch (ImagickException $error) {
                throw new RuntimeException('The native encoder could not write the gallery image.', 0, $error);
            } finally {
                fclose($stream);
            }
        }

        if (!$success) {
            throw new RuntimeException('The native encoder could not write the gallery image.');
        }
    }

    /** Probe actual ImageMagick encoding and cache the result for this PHP process. @since 1.9.0 */
    private static function canEncodeWithImagick(int $type): bool
    {
        if (!class_exists(Imagick::class)) {
            return false;
        }
        if (array_key_exists($type, self::$imagickEncoders)) {
            return self::$imagickEncoders[$type];
        }

        $format = self::READ_WRITE_FORMATS[$type][0];
        try {
            $probe = new Imagick();
            if (Imagick::queryFormats($format) !== [] && $probe->newImage(2, 2, 'black', $format)) {
                $metadata = @getimagesizefromstring($probe->getImageBlob());
                return self::$imagickEncoders[$type] = $metadata !== false
                    && [$metadata[0], $metadata[1], $metadata[2]] === [2, 2, $type];
            }
        } catch (ImagickException) {
            // A listed format may have a decoder but no encoder.
        }
        return self::$imagickEncoders[$type] = false;
    }

    /** Use a local file handle so the path is never interpreted as an ImageMagick expression. @since 1.9.0 */
    private static function readWithImagick(string $path, string $format): ?Imagick
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return null;
        }

        try {
            $metadata = self::pingImagick($stream, $format);
            $fileBytes = (int) (fstat($stream)['size'] ?? 0);
            if ($metadata === null
                || !self::hasMemoryFor($metadata->getImageWidth(), $metadata->getImageHeight(), $fileBytes) || !rewind($stream)) {
                return null;
            }
            $image = new Imagick();
            if (!$image->setFormat($format) || !$image->readImageFile($stream) || $image->getNumberImages() !== 1
                || $image->getImageWidth() !== $metadata->getImageWidth() || $image->getImageHeight() !== $metadata->getImageHeight()) {
                return null;
            }
            return $image;
        } catch (ImagickException) {
            return null;
        } finally {
            fclose($stream);
        }
    }

    /** Read a single frame header through the selected ImageMagick coder. @since 1.9.0 */
    private static function pingImagick($stream, string $format): ?Imagick
    {
        $image = new Imagick();
        return $image->setFormat($format) && $image->pingImageFile($stream) && $image->getNumberImages() === 1 ? $image : null;
    }

    /** Normalize explicit image formats and the JPEG extension alias. @since 1.9.0 */
    private static function type(string $format): int
    {
        return match (strtolower($format)) {
            'jpeg', 'jpg' => IMAGETYPE_JPEG,
            'png' => IMAGETYPE_PNG,
            'gif' => IMAGETYPE_GIF,
            'webp' => IMAGETYPE_WEBP,
            'avif' => IMAGETYPE_AVIF,
            default => throw new InvalidArgumentException('Unsupported native image format.'),
        };
    }

    /** Check native raster buffers against the available runtime memory. @since 1.9.0 */
    private static function hasMemoryFor(int $width, int $height, int $fileBytes = 0): bool
    {
        return $width > 0 && $height > 0 &&
            ImageMemoryBudget::allows((float) $width * $height, $fileBytes, PHP_SAPI === 'cli');
    }
}
