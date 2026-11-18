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
        [$width, $height] = $this->image instanceof GdImage
            ? [imagesx($this->image), imagesy($this->image)]
            : [$this->image->getImageWidth(), $this->image->getImageHeight()];
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
