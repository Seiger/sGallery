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
        IMAGETYPE_BMP => ['BMP', 'imagecreatefrombmp', 'imagebmp', 'BMP Support'],
        IMAGETYPE_ICO => ['ICO', null, null, null],
        IMAGETYPE_WBMP => ['WBMP', 'imagecreatefromwbmp', 'imagewbmp', 'WBMP Support'],
    ];

    private const READ_ONLY_FORMATS = [
        IMAGETYPE_TIFF_II => ['TIFF', null],
        IMAGETYPE_TIFF_MM => ['TIFF', null],
    ];

    private static array $imagickEncoders = [];
    private bool $expectSrgb = false;
    private bool $optimizeEncoding = false;

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
     * let Imagick probe dimensions when PHP cannot read a HEIC/HEIF or JPEG XL header.
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
        $imagickFormat = $input === null ? self::imagickInputFormat($path) : null;
        if ($imagickFormat !== null) {
            $input = [$imagickFormat, null];
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

    /** Verify the actual raster type, including formats reserved for the thumbnail helper. */
    public static function hasFormat(string $path, string $format): bool
    {
        return (@getimagesize($path)[2] ?? null) === self::type($format);
    }

    /** Decode only the camera's embedded JPEG thumbnail from a validated local source. */
    public static function openExifThumbnail(string $source, string $format, string $driver): self
    {
        $path = GalleryFiles::local($source);
        $bytes = $path !== null && function_exists('exif_thumbnail') ? @exif_thumbnail($path) : false;
        $metadata = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($metadata === false || $metadata[2] !== IMAGETYPE_JPEG ||
            !self::hasMemoryFor($metadata[0], $metadata[1], strlen($bytes))) {
            throw new RuntimeException('No usable embedded JPEG thumbnail.');
        }
        if ($driver === 'imagick') {
            $image = new Imagick();
            if (!$image->readImageBlob($bytes) || $image->getNumberImages() !== 1) {
                throw new RuntimeException('Cannot decode the embedded JPEG thumbnail.');
            }
        } else {
            $image = @imagecreatefromstring($bytes);
            if ($image === false) {
                throw new RuntimeException('Cannot decode the embedded JPEG thumbnail.');
            }
        }
        return new self($image, self::type($format), $path);
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
        return $outputCapability !== null && !empty($capabilities[$outputCapability]) &&
            function_exists($decoder) && function_exists($encoder);
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

    /** Identify HEIC/HEIF and JPEG XL codestreams or containers from local contents. @since 1.9.0 */
    public static function imagickInputFormat(string $path): ?string
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return null;
        }
        try {
            $header = fread($stream, 12);
        } finally {
            fclose($stream);
        }
        if (is_string($header) && (str_starts_with($header, "\xff\x0a") || $header === "\0\0\0\x0cJXL \x0d\x0a\x87\x0a")) {
            return 'JXL';
        }
        return class_exists(\finfo::class) &&
            in_array((new \finfo(FILEINFO_MIME_TYPE))->file($path), ['image/heic', 'image/heif'], true) ? 'HEIC' : null;
    }

    /**
     * Probe an optional Imagick input before decoding, with a compressed-file memory check.
     * Require a single image so an animated input cannot become a still derivative.
     * @return array{dimensions: array{int, int, int}, alpha: bool, icc: bool, orientation: int}|null
     * @since 1.9.0
     */
    public static function inspectImagickInput(string $path, string $format, bool $background = false): ?array
    {
        if (!class_exists(Imagick::class) || !in_array($format, ['HEIC', 'JXL', 'GIF', 'WEBP', 'AVIF', 'BMP', 'TIFF'], true) ||
            Imagick::queryFormats($format) === [] || !ImageMemoryBudget::allows(0, (int) @filesize($path), $background)) {
            return null;
        }
        $stream = @fopen($path, 'rb');
        if ($stream === false) return null;
        try {
            $image = self::pingImagick($stream, $format);
            if ($image === null || $image->getImageWidth() < 1 || $image->getImageHeight() < 1) {
                return null;
            }
            $icc = $image->getImageProfiles('icc', false) !== [];
            if (!$icc && !in_array($image->getImageColorspace(), [Imagick::COLORSPACE_SRGB, Imagick::COLORSPACE_GRAY], true)) {
                return null;
            }
            $orientation = $format === 'JXL' ? 1 : $image->getImageOrientation();
            return [
                'dimensions' => [$image->getImageWidth(), $image->getImageHeight(), 0],
                'alpha' => $image->getImageAlphaChannel(),
                'icc' => $icc,
                'orientation' => $orientation >= 2 && $orientation <= 8 ? $orientation : 1,
            ];
        } catch (ImagickException) {
            return null;
        } finally {
            fclose($stream);
        }
    }

    /**
     * Inspect newly supported still rasters without decoding pixels before the queue budget check.
     * TIFF requires a single-page Imagick probe; unsupported metadata retains the original.
     * @return array{dimensions: array, alpha: bool, icc: bool, orientation: int}|null
     * @since 1.9.0
     */
    public static function inspectRasterInput(string $path, array $dimensions, bool $background = false): ?array
    {
        $type = $dimensions[2] ?? null;
        $format = (self::READ_WRITE_FORMATS[$type] ?? self::READ_ONLY_FORMATS[$type] ?? [null])[0];
        if (!in_array($format, ['GIF', 'WEBP', 'AVIF', 'BMP', 'TIFF', 'ICO', 'WBMP'], true)) return null;
        if (class_exists(Imagick::class) && Imagick::queryFormats($format) !== []) {
            $metadata = self::inspectImagickInput($path, $format, $background);
            // Some WebP ping implementations omit ICC even though the decoder exposes it.
            $color = in_array($format, ['WEBP', 'AVIF', 'BMP'], true) ? ImageColor::fromRaster($path, $type)[1] : ImageColor::SRGB;
            if ($metadata !== null && !$metadata['icc'] && $color === ImageColor::UNSUPPORTED) return null;
            // TIFF ping can omit its ExtraSamples alpha channel; retain it conservatively.
            if ($metadata !== null) return [...$metadata, 'dimensions' => $dimensions,
                'alpha' => $metadata['alpha'] || in_array($format, ['TIFF', 'ICO'], true), 'icc' => $metadata['icc'] || $color === ImageColor::ICC];
        }
        if (in_array($format, ['TIFF', 'ICO'], true)) return null;
        if ($format === 'WBMP') {
            return ['dimensions' => $dimensions, 'alpha' => false, 'icc' => false, 'orientation' => 1];
        }
        [$alpha, $color] = ImageColor::fromRaster($path, $type);
        return $color === ImageColor::UNSUPPORTED ? null : [
            'dimensions' => $dimensions, 'alpha' => $alpha, 'icc' => $color === ImageColor::ICC, 'orientation' => 1,
        ];
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

    /** Convert the embedded source profile to sRGB before geometry or compositing. @since 1.9.0 */
    public function toSrgb(): self
    {
        if (!$this->image instanceof Imagick) {
            throw new RuntimeException('The gallery color transform requires Imagick with ICC support.');
        }
        $delegates = Imagick::getConfigureOptions('DELEGATES')['DELEGATES'] ?? '';
        if (!preg_match('/\blcms2?\b/i', $delegates) || $this->image->getImageProfiles('icc', false) === []) {
            throw new RuntimeException('The native image driver cannot apply the source ICC profile.');
        }
        [$width, $height] = $this->dimensions();
        if (!self::hasMemoryFor($width, $height)) {
            throw new RuntimeException('Insufficient memory budget to convert the gallery image color.');
        }
        $image = clone $this->image;
        $profile = ImageColor::srgbProfile();
        if (!$image->profileImage('icc', $profile) || $image->getImageProfile('icc') !== $profile) {
            throw new RuntimeException('The native image driver could not convert the gallery image to sRGB.');
        }

        return new self($image, $this->outputType, $this->source);
    }

    /** Tag the finished sRGB frame, including a new canvas created by a fit operation. @since 1.9.0 */
    public function tagSrgb(): void
    {
        $image = $this->image;
        if (!$image instanceof Imagick || !$image->setImageProfile('icc', ImageColor::srgbProfile())) {
            throw new RuntimeException('The native image driver could not attach the gallery sRGB profile.');
        }
        // Grayscale PNG samples cannot carry an RGB ICC profile.
        if ($this->outputType === IMAGETYPE_PNG &&
            (!$image->setOption('png:color-type', $image->getImageAlphaChannel() ? '6' : '2') ||
                !$image->setOption('png:exclude-chunk', 'sRGB'))) {
            throw new RuntimeException('The native image driver could not select an RGB PNG output.');
        }
        $this->expectSrgb = true;
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

    /**
     * Sharpen the frame's color channels while keeping its original opacity.
     * Call after resampling and before compositing any watermark or output background.
     *
     * @param float $amount Strength from 0 to 100 after Builder validation.
     * @since 1.9.0
     */
    public function sharpen(float $amount): self
    {
        if (!is_finite($amount) || $amount < 0 || $amount > 100) {
            throw new InvalidArgumentException('Gallery sharpening strength must be between 0 and 100.');
        }
        if ($amount === 0.0) {
            return $this;
        }

        [$width, $height] = $this->dimensions();
        if (!self::hasMemoryFor($width, $height)) {
            throw new RuntimeException('Insufficient memory budget to sharpen the gallery image.');
        }

        $image = $this->withPixelAlpha();
        $frame = $image->image;
        if ($frame instanceof Imagick) {
            $channels = Imagick::CHANNEL_RED | Imagick::CHANNEL_GREEN | Imagick::CHANNEL_BLUE;
            $success = $frame->unsharpMaskImage(1, 1, $amount / 6.25, 0, $channels);
        } else {
            $corner = $amount >= 10 ? -$amount * 0.01 : 0.0;
            $edge = -$amount * 0.025;
            $center = 1 - 4 * ($corner + $edge);
            $kernel = [[$corner, $edge, $corner], [$edge, $center, $edge], [$corner, $edge, $corner]];
            // Convolution writes pixels back with their original alpha. Blending would compound it.
            $success = function_exists('imageconvolution') && imagealphablending($frame, false) &&
                imageconvolution($frame, $kernel, 1, 0);
        }
        if (!$success) {
            throw new RuntimeException('The native image driver could not sharpen the gallery image.');
        }
        return $image;
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

    /** Give GD's GIF encoder one transparent palette index for pixels below half opacity. @since 1.9.0 */
    public function withGifTransparency(): self
    {
        if (!$this->image instanceof GdImage) {
            return $this;
        }

        [$width, $height] = $this->dimensions();
        if (!self::hasMemoryFor($width, $height)) {
            throw new RuntimeException('Insufficient memory budget to prepare the GIF palette.');
        }

        $palette = self::newGdFrame($width, $height);
        if (!imagecopy($palette, $this->image, 0, 0, 0, 0, $width, $height) || !imagetruecolortopalette($palette, false, 255)) {
            throw new RuntimeException('GD could not prepare the GIF palette.');
        }
        $transparent = imagecolorallocatealpha($palette, 0, 0, 0, 127);
        if ($transparent === false) {
            throw new RuntimeException('GD could not allocate the transparent GIF palette entry.');
        }
        imagecolortransparent($palette, $transparent);
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                if (((imagecolorat($this->image, $x, $y) >> 24) & 0x7f) >= 64) {
                    imagesetpixel($palette, $x, $y, $transparent);
                }
            }
        }

        return new self($palette, $this->outputType, $this->source);
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

    /** Keep the source identity and requested format while replacing the decoded frame. */
    public function withFrame(GdImage|Imagick $frame): self
    {
        $result = new self($frame, $this->outputType, $this->source);
        $result->expectSrgb = $this->expectSrgb;
        $result->optimizeEncoding = $this->optimizeEncoding;
        return $result;
    }

    /** Reduce the palette on a copy so the full-color candidate remains available. */
    public function limitColors(int $colors, bool $dither = false): self
    {
        [$width, $height] = $this->dimensions();
        if (!self::hasMemoryFor($width, $height)) {
            throw new RuntimeException('Insufficient memory budget to reduce thumbnail colors.');
        }
        if ($this->image instanceof Imagick) {
            $image = clone $this->image;
            $success = $image->quantizeImage($colors, Imagick::COLORSPACE_SRGB, 0, $dither, false);
        } else {
            $image = self::cropFrame($this->image, 0, 0, $width, $height);
            $success = (!imageistruecolor($image) ? imagepalettetotruecolor($image) : true) &&
                imagetruecolortopalette($image, $dither, $colors);
        }
        if (!$success) {
            throw new RuntimeException('Cannot reduce the thumbnail color palette.');
        }
        return $this->withFrame($image);
    }

    /** Source substitutions understood by the legacy text watermark. */
    public function sourceMetrics(): array
    {
        $dimensions = @getimagesize($this->source);
        $bytes = (int) @filesize($this->source);
        return ['^X' => $dimensions[0] ?? 0, '^Y' => $dimensions[1] ?? 0, '^Fb' => $bytes,
            '^Fk' => round($bytes / 1024), '^Fm' => round($bytes / 1048576)];
    }

    /** Place the frame on a thumbnail canvas at a caller-selected position. */
    public function onCanvas(int $width, int $height, int $x, int $y, ?string $color): self
    {
        if (!self::hasMemoryFor($width, $height)) {
            throw new RuntimeException('Insufficient memory budget for the thumbnail canvas.');
        }
        if ($this->image instanceof Imagick) {
            $canvas = new Imagick();
            $success = $canvas->newImage($width, $height, $color === null ? 'transparent' : '#' . $color, 'PNG') &&
                $canvas->setImageAlphaChannel($color === null ? Imagick::ALPHACHANNEL_ACTIVATE : Imagick::ALPHACHANNEL_OPAQUE) &&
                $canvas->compositeImage($this->image, Imagick::COMPOSITE_OVER, $x, $y) &&
                ($color === null || $canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE));
        } else {
            $canvas = self::newGdFrame($width, $height);
            $success = imagefill($canvas, 0, 0, $color === null ? 0x7f000000 : (int) hexdec($color)) &&
                imagealphablending($canvas, $color !== null) &&
                imagecopy($canvas, $this->image, $x, $y, 0, 0, imagesx($this->image), imagesy($this->image));
        }
        if (!$success) {
            throw new RuntimeException('Cannot compose the thumbnail canvas.');
        }
        return $this->withFrame($canvas);
    }

    /** Rotate counterclockwise by the legacy helper's angle convention. */
    public function rotate(float $angle, ?string $color = null): self
    {
        if ($angle === 0.0) {
            return $this;
        }
        [$width, $height] = $this->dimensions();
        $radians = deg2rad($angle);
        $cos = abs(cos($radians));
        $sin = abs(sin($radians));
        if (!ImageMemoryBudget::allows(($width * $cos + $height * $sin) * ($height * $cos + $width * $sin), 0, PHP_SAPI === 'cli')) {
            throw new RuntimeException('Insufficient memory budget to rotate the thumbnail.');
        }

        if ($this->image instanceof Imagick) {
            $image = clone $this->image;
            if (!$image->rotateImage($color === null ? 'transparent' : '#' . $color, -$angle) ||
                !$image->setImagePage(0, 0, 0, 0)) {
                throw new RuntimeException('Cannot rotate the thumbnail.');
            }
        } else {
            $image = imagerotate($this->withPixelAlpha()->frame(), $angle, $color === null ? 0x7f000000 : (int) hexdec($color));
            if ($image === false) {
                throw new RuntimeException('Cannot rotate the thumbnail.');
            }
            imagesavealpha($image, true);
        }
        return $this->withFrame($image);
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

    /**
     * Enable native compression options and strip non-color metadata during encoding.
     * Keep the caller's quality. The usual staged-file decode still precedes publication.
     * @since 1.9.0
     */
    public function optimize(bool $enabled = true): self
    {
        $this->optimizeEncoding = $enabled;
        return $this;
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
            return $decoded !== null && $decoded->getImageWidth() === $width && $decoded->getImageHeight() === $height &&
                (!$this->expectSrgb || $decoded->getImageProfile('icc') === ImageColor::srgbProfile());
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
            $interlaced = imageinterlace($this->image);
            try {
                if ($this->optimizeEncoding && $this->outputType === IMAGETYPE_JPEG) {
                    imageinterlace($this->image, true);
                }
                $success = match ($this->outputType) {
                    IMAGETYPE_PNG => @$encoder($this->image, $path, $this->optimizeEncoding ? 9 : min(9, (int) round((100 - $quality) / 10))),
                    IMAGETYPE_GIF, IMAGETYPE_BMP, IMAGETYPE_WBMP => @$encoder($this->image, $path),
                    default => @$encoder($this->image, $path, $quality),
                };
            } finally {
                imageinterlace($this->image, $interlaced);
            }
        } else {
            $stream = @fopen($path, 'wb');
            if ($stream === false) {
                throw new RuntimeException('Cannot open the temporary gallery image.');
            }
            try {
                $image = $this->optimizeEncoding ? clone $this->image : $this->image;
                if ($this->optimizeEncoding) {
                    self::optimizeImagickFrame($image, $format);
                }
                $success = $image->setImageFormat($format)
                    && $image->setImageCompressionQuality($quality)
                    && $image->setCompressionQuality($format === 'PNG' ? 100 - $quality : $quality)
                    && $image->writeImageFile($stream, $format)
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

    /**
     * Configure one Imagick frame, including a frame owned by the animation writer.
     * ICC and pixel opacity survive metadata stripping. Frame timing belongs to the writer.
     * @since 1.9.0
     */
    public static function optimizeImagickFrame(Imagick $image, string $format): void
    {
        $format = strtoupper($format);
        $hasProfile = $image->getImageProfiles('icc', false) !== [];
        foreach ($image->getImageProfiles('*', false) as $profile) {
            if (!in_array(strtolower($profile), ['icc', 'icm'], true)) {
                $image->removeImageProfile($profile);
            }
        }
        foreach ($image->getImageProperties('*', false) as $property) {
            if (!$image->deleteImageProperty($property)) {
                throw new RuntimeException('Cannot remove gallery image metadata.');
            }
        }
        $options = match ($format) {
            'JPEG', 'JPG' => ['jpeg:optimize-coding' => 'true'],
            'PNG' => ['png:compression-level' => '9', 'png:compression-filter' => '5', 'png:exclude-chunk' => 'date,time'],
            'WEBP' => ['webp:method' => '6'],
            default => [],
        };
        foreach ($options as $key => $value) {
            if (!$image->setOption($key, $value)) {
                throw new RuntimeException('Cannot configure native gallery compression.');
            }
        }
        if (in_array($format, ['JPEG', 'JPG'], true) && !$image->setInterlaceScheme(Imagick::INTERLACE_PLANE)) {
            throw new RuntimeException('Cannot configure progressive gallery JPEG encoding.');
        }
        if ($hasProfile && $format === 'PNG' &&
            (!$image->setOption('png:color-type', $image->getImageAlphaChannel() ? '6' : '2') ||
                !$image->setOption('png:exclude-chunk', 'sRGB,date,time'))) {
            throw new RuntimeException('Cannot preserve the gallery PNG color profile.');
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
            'bmp' => IMAGETYPE_BMP,
            'ico' => IMAGETYPE_ICO,
            'wbmp' => IMAGETYPE_WBMP,
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
