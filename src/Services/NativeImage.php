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

        if ($image instanceof Imagick) {
            if ($crop && $image->setImagePage(0, 0, 0, 0) &&
                $image->cropImage($width, $height, -$x, -$y) && $image->setImagePage(0, 0, 0, 0)) {
                return $image;
            }
            if (!$crop) {
                $canvas = new Imagick();
                if ($canvas->newImage($width, $height, 'white', 'PNG') &&
                    $canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE) &&
                    $canvas->compositeImage($image, Imagick::COMPOSITE_COPY, $x, $y)) {
                    return $canvas;
                }
            }
        } else {
            $canvas = imagecreatetruecolor($width, $height);
            if ($canvas !== false) {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
                if (($crop || imagefill($canvas, 0, 0, 0xffffff)) &&
                    imagefilledrectangle($canvas, $x, $y, $x + $imageWidth - 1, $y + $imageHeight - 1, $transparent) &&
                    imagecopy($canvas, $image, $x, $y, 0, 0, $imageWidth, $imageHeight)) {
                    return $canvas;
                }
            }
        }

        throw new RuntimeException('The native image driver could not center the gallery image.');
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

        $frame = imagecreatetruecolor($width, $height);
        if ($frame === false) {
            throw new RuntimeException('GD could not allocate the gallery frame.');
        }
        imagealphablending($frame, false);
        imagesavealpha($frame, true);
        if (!imagecopyresampled($frame, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
            throw new RuntimeException('GD could not resize the gallery image.');
        }
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
            $metadata = new Imagick();
            $fileBytes = (int) (fstat($stream)['size'] ?? 0);
            if (!$metadata->setFormat($format) || !$metadata->pingImageFile($stream) || $metadata->getNumberImages() !== 1
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
