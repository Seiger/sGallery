<?php namespace Seiger\sGallery\Builders;

use Illuminate\View\View;
use Seiger\sGallery\Controllers\sGalleryController;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\sGallery;
use Seiger\sGallery\Exceptions\OptimizationDeferred;
use Seiger\sGallery\Services\GalleryCache;
use Seiger\sGallery\Services\GalleryFiles;
use Seiger\sGallery\Services\GalleryMedia;
use Seiger\sGallery\Services\ImageAnimation;
use Seiger\sGallery\Services\ImageColor;
use Seiger\sGallery\Services\ImageMemoryBudget;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Seiger\sGallery\Services\ImageSource;
use Seiger\sGallery\Services\NativeAnimation;
use Seiger\sGallery\Services\NativeImage;
use Seiger\sGallery\Services\PhpThumbOptions;
use Seiger\sGallery\Services\Watermark;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Class sGalleryBuilder
 *
 * A fluent builder class for configuring and retrieving gallery data with comprehensive
 * image manipulation capabilities. This class provides a chainable interface for:
 *
 * - Gallery view configuration (tabs, sections, downloads)
 * - File manipulation (resizing, cropping, format conversion)
 * - Browser-based format detection (AVIF, WebP, JPEG)
 * - Image processing with transparency support
 * - Caching and optimization
 */
class sGalleryBuilder
{
    private const OUTPUT_FORMATS = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'original'];

    protected string $mode = 'view';
    protected string $viewType = sGalleryModel::VIEW_SECTION;
    protected string $itemType = 'resource';
    protected ?string $type = null;
    protected string $idType = 'i';
    protected ?string $blockName = '1';
    protected ?int $documentId = null;
    protected int $quality = 100;
    protected bool $qualityExplicit = false;
    private bool $optimizationExplicit = false;
    protected ?string $lang = null;
    protected ?\Illuminate\Database\Eloquent\Builder $query = null;
    protected ?\Illuminate\Database\Eloquent\Collection $files = null;
    protected ?string $file = null;
    protected ?array $params = [];
    protected ?bool $sourceHasAlpha = null;
    protected array $alphaDetectionCache = [];
    protected bool $backgroundOptimization = false;
    protected float $optimizationStartedAt = 0;
    protected array $optimizationJob = [];
    private ?array $queuedSourceVersion = null;
    private ?array $metadataSourceVersion = null;

    /**
     * Set the mode for the builder (e.g., 'view' or 'collections').
     *
     * @param string $mode Mode for the builder, e.g., 'collections' or 'view'.
     * @return $this
     */
    public function setMode(string $mode): self
    {
        if ($mode == 'collections') {
            $this->initQuery();
        }
        $this->mode = $mode;
        return $this;
    }

    /**
     * Set the view type.
     *
     * @param string $viewType View type (e.g., tab, section).
     * @return $this
     */
    public function viewType(string $viewType): self
    {
        if (in_array($viewType, [
            sGalleryModel::VIEW_TAB,
            sGalleryModel::VIEW_SECTION,
            sGalleryModel::VIEW_SECTION_DOWNLOADS
        ])) {
            $this->viewType = $viewType;
        }
        return $this;
    }

    /**
     * Set the item type (resource type).
     *
     * @param string $itemType Resource type (e.g., 'resource', 'product', 'gallery').
     * @return $this
     */
    public function itemType(string $itemType): self
    {
        $this->itemType = $itemType;
        return $this;
    }

    /**
     * Filter the collection by the stored media type.
     *
     * @param string $type Media type, such as image, youtube, or pdf.
     * @return $this
     *
     * @since 1.5.1
     */
    public function mediaType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Set the document ID type.
     *
     * @param string $idType Document ID type.
     * @return $this
     */
    public function idType(string $idType): self
    {
        $this->idType = $idType;
        return $this;
    }

    /**
     * Set the block name.
     *
     * @param string $blockName Name of the block.
     * @return $this
     */
    public function blockName(string $blockName): self
    {
        $this->blockName = $blockName;
        return $this;
    }

    /**
     * Set the block name to null and return the builder instance.
     * Used to retrieve all files without filtering by block name.
     *
     * @return $this
     */
    public function all(): self
    {
        $this->blockName = null;
        return $this;
    }

    /**
     * Set the document ID.
     *
     * @param int $documentId ID of the document.
     * @return $this
     */
    public function documentId(int $documentId): self
    {
        $this->documentId = $documentId;
        return $this;
    }

    /**
     * Set the language for the query.
     *
     * @param string $lang Language code (e.g., 'en', 'uk').
     * @return $this
     */
    public function lang(string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    /**
     * Configure an explicit or default watermark for the selected image driver.
     * @param string|array|null $file Local image path, options, or default configuration
     * @param array $options Position, width, opacity and padding overrides
     * @return $this
     * @since 1.7.0
     */
    public function watermark(string|array|null $file = null, array $options = []): self
    {
        if (is_array($file)) { $options = $file; $file = null; }
        $defaults = (array) config('seiger.settings.sGallery.watermark', []);
        $file = $file ?? ($defaults['file'] ?? '');
        unset($this->params['watermark']);
        if ($file === '') return $this;
        $path = Watermark::path($file);
        unset($defaults['file']);
        $this->params['watermark'] = ['file' => $file, 'hash' => hash_file('sha256', $path), 'options' => Watermark::options(array_replace($defaults, $options))];
        return $this;
    }

    /**
     * Select the output format for the processed image.
     *
     * Accepts JPG/JPEG, PNG, WebP, AVIF and GIF, subject to driver support.
     * The `original` mode preserves the source image extension.
     * Values are case-insensitive; unsupported values leave the current format unchanged.
     *
     * @param string $format jpg, jpeg, png, webp, avif, gif or original.
     * @return $this
     */
    public function format(string $format): self
    {
        $format = strtolower(trim($format));

        if (in_array($format, self::OUTPUT_FORMATS, true)) {
            $this->params['format'] = $format;
        }

        return $this;
    }

    /**
     * Set a fixed encoding quality, clamped to 1 through 100.
     * Explicit quality bypasses the adaptive WebP candidate search.
     *
     * @param int $quality Quality percentage (e.g., 80).
     * @return $this
     */
    public function quality(int $quality): self
    {
        $this->qualityExplicit = true;
        $this->quality = max(1, min(100, $quality));
        return $this;
    }

    /**
     * Sharpen the processed image after resize/crop transformations.
     *
     * @param float $amount Sharpen amount from 0 to 100.
     * @return $this
     */
    public function sharpen(float $amount): self
    {
        $this->params['sharpen'] = max(0, min(100, $amount));
        return $this;
    }

    /**
     * Enable output optimization, including native compression and adaptive WebP quality.
     *
     * @param bool $optimize Whether to optimize the output image.
     * @return $this
     */
    public function optimize(bool $optimize = true): self
    {
        $this->optimizationExplicit = true;
        $this->params['optimize'] = $optimize;
        return $this;
    }

    /**
     * Set the resize dimensions for the image.
     *
     * @param int $width Width of the image.
     * @param int|null $height Height of the image (optional, defaults to the width if not provided).
     * @return $this
     */
    public function resize(int $width, int|null $height = null): self
    {
        $this->setDimensions($width, $height ?: $width);
        return $this;
    }

    /**
     * Set the fit method and dimensions for the image.
     *
     * @param string $method Fit method (e.g., 'crop', 'contain').
     * @param int $width Width of the image.
     * @param int|null $height Height of the image (optional, defaults to the width if not provided).
     * @return $this
     */
    public function fit(string $method, int $width, int|null $height = null): self
    {
        $this->setDimensions($width, $height ?: $width);
        $mode = in_array($method, NativeImage::FIT_MODES, true) ? $method : 'crop';
        $this->params['fit'] = $mode;

        return $this;
    }

    /**
     * Set the crop method and dimensions for the image.
     *
     * @param string $method Crop position (e.g., 'center', 'left', 'right').
     * @param int $width Width of the image.
     * @param int|null $height Height of the image (optional, defaults to the width if not provided).
     * @return $this
     */
    public function crop(string $method, int $width, int|null $height = null): self
    {
        $this->setDimensions($width, $height ?: $width);
        $position = isset(NativeImage::CROP_POSITIONS[$method]) ? $method : 'center';
        $this->params['crop'] = $position;
        return $this;
    }

    /**
     * Center a crop on a pixel in the source image.
     *
     * @param int $centerX The X coordinate in source pixels.
     * @param int $centerY The Y coordinate in source pixels.
     * @param int $width The width in pixels (minimum 1)
     * @param int|null $height The height in pixels (optional, defaults to width)
     * @return $this Fluent interface for method chaining
     *
     * @example
     * $builder->focalCrop(50, 30, 400, 300);
     */
    public function focalCrop(int $centerX, int $centerY, int $width, int|null $height = null): self
    {
        $this->setDimensions($width, $height ?: $width);

        $this->params['focalCenterX'] = max(0, $centerX);
        $this->params['focalCenterY'] = max(0, $centerY);
        return $this;
    }

    /**
     * Set manual crop with specific start coordinates.
     *
     * @param int $startX The X coordinate to start cropping from (minimum 0)
     * @param int $startY The Y coordinate to start cropping from (minimum 0)
     * @param int $width The width in pixels (minimum 1)
     * @param int|null $height The height in pixels (optional, defaults to width)
     * @return $this Fluent interface for method chaining
     *
     * @example
     * $builder->manualCrop(100, 50, 400, 300);
     */
    public function manualCrop(int $startX, int $startY, int $width, int|null $height = null): self
    {
        $this->setDimensions($width, $height ?: $width);

        $this->params['startX'] = max(0, $startX);
        $this->params['startY'] = max(0, $startY);
        return $this;
    }

    /** Set output dimensions while retaining the fluent API minimum of one pixel. @since 1.8.0 */
    private function setDimensions(int $width, int $height): void
    {
        $this->params['w'] = max(1, $width);
        $this->params['h'] = max(1, $height);
    }

    /** Require both dimensions to be positive integers. @since 1.8.0 */
    private static function validDimensions(mixed $width, mixed $height): bool
    {
        return is_int($width) && is_int($height) && $width > 0 && $height > 0;
    }

    /**
     * Load files for the 'collections' mode if not already loaded.
     * This method initializes the query and filters files by language, document ID, item type, and block name (if provided).
     *
     * @return void
     */
    protected function loadFiles(): void
    {
        if (is_null($this->files)) {
            $this->initQuery();
            $query = $this->query->lang($this->lang ?? evo()->getLocale())
                ->whereParent($this->documentId ?? evo()->documentIdentifier)
                ->whereItemType($this->itemType);

            if ($this->blockName) {
                $query->whereBlock($this->blockName);
            }

            if ($this->type !== null) {
                $query->whereType($this->type);
            }

            $this->files = $query->orderBy('position')->get();
        }
    }

    /**
     * Execute the query and return the collection of files.
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Seiger\sGallery\Models\sGalleryModel[]
     */
    public function get(): \Illuminate\Database\Eloquent\Collection
    {
        $this->loadFiles();
        $files = $this->files;
        $this->resetBuilder();
        return $files;
    }

    /**
     * Get the file by its position in the collection.
     *
     * @param int $index Position index (1-based).
     * @return \Seiger\sGallery\Models\sGalleryModel|null
     */
    public function eq(int $index): ?\Seiger\sGallery\Models\sGalleryModel
    {
        $this->loadFiles();
        $files = $this->files->get($index - 1);
        $this->resetBuilder();
        return $files;
    }

    /** Configure an isolated thumbnail request using the helper's output defaults. @internal */
    public function thumbnail(PhpThumbOptions $options, string $format, string $baseFormat, int $quality): self
    {
        $this->params = [
            'format' => $format,
            'phpThumb' => $options->query(),
            'phpThumbBase' => $baseFormat,
            'phpThumbAssets' => $options->assetRevision(),
        ];
        return $this->quality($quality);
    }

    /** Keep Blade thumbnails under the cache directory served by Evolution's webserver templates. @internal */
    public function usePublicThumbnailCache(): self
    {
        $this->params['publicThumbnail'] = true;
        return $this;
    }

    /**
     * Get the URL of the processed file (resized, formatted).
     *
     * Slow web transformations return the original and optionally enqueue a versioned
     * sTask job. Empty cache files are regenerated. The cooperative deadline cannot
     * interrupt a native encoder already executing. Remote URLs are returned to
     * the browser without fetching them. Uncached work is estimated before decoding.
     * Cache identity includes the source contents; publication rechecks the pinned revision.
     *
     * @return string URL of the processed image or 'no image' placeholder.
     */
    public function getFile(): string
    {
        $sourceFile = $this->file;
        if (sGallery::hasLink($sourceFile)) {
            return GalleryMedia::link($sourceFile) ? $sourceFile : sGalleryModel::NOIMAGE;
        }

        $this->optimizationStartedAt = microtime(true);
        $localPath = $sourceFile === null ? null : GalleryFiles::local($sourceFile);
        if ($localPath !== null) {
            $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

            if ($this->params !== null && $extension !== 'svg') {
                $thumbnail = isset($this->params['phpThumb']) ? PhpThumbOptions::parse($this->params['phpThumb']) : null;
                if ($thumbnail !== null && !$this->thumbnailAssetsMatch($thumbnail)) {
                    return $this->originalImageUrl();
                }
                $sourceVersion = ImageSource::version($localPath);
                if ($sourceVersion === null || ($this->queuedSourceVersion !== null && $sourceVersion !== $this->queuedSourceVersion)) {
                    if ($this->backgroundOptimization) throw new \RuntimeException('Image optimization source changed while queued.');
                    return $this->originalImageUrl();
                }
                if ($sourceVersion !== $this->metadataSourceVersion) {
                    $this->sourceHasAlpha = null;
                    $this->alphaDetectionCache = [];
                    $this->metadataSourceVersion = $sourceVersion;
                }
                $dimensions = @getimagesize($localPath);
                $nativeInputFormat = in_array($dimensions[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)
                    ? null : NativeImage::imagickInputFormat($localPath);
                $nativeInput = null;
                if ($nativeInputFormat !== null) {
                    if (($this->params['format'] ?? null) === 'original' || !$this->shouldTransform(false)) {
                        return $this->originalImageUrl();
                    }
                    $nativeInput = NativeImage::inspectImagickInput($localPath, $nativeInputFormat, $this->backgroundOptimization);
                    if ($nativeInput === null) return $this->originalImageUrl();
                    $dimensions = $nativeInput['dimensions'];
                }
                $animated = ImageAnimation::isAnimated($localPath, $dimensions[2] ?? 0);
                if (!$animated && $this->shouldTransform(false) && in_array($dimensions[2] ?? null,
                    [IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_AVIF, IMAGETYPE_BMP, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM,
                        IMAGETYPE_ICO, IMAGETYPE_WBMP], true)) {
                    $nativeInput = NativeImage::inspectRasterInput($localPath, $dimensions, $this->backgroundOptimization);
                    if ($nativeInput === null) return $this->originalImageUrl();
                }
                if (!$this->shouldTransform($animated) ||
                    (!$animated && $nativeInput === null && !in_array($dimensions[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true))) {
                    return $this->originalImageUrl();
                }
                if (!self::validDimensions($dimensions[0], $dimensions[1])) {
                    return sGalleryModel::NOIMAGE;
                }
                $imageName = str_replace('.' . pathinfo($this->file, PATHINFO_EXTENSION), '', pathinfo($this->file, PATHINFO_BASENAME));
                $chacheFile = GalleryCache::directoryFor($this->file, ($this->params['publicThumbnail'] ?? false) === true);
                $nativeAlpha = false;
                $nativeIcc = false;
                $nativeTiming = null;
                $nativeAnimationFormat = null;
                if ($animated) {
                    $nativeAnimationFormat = $dimensions[2] === IMAGETYPE_GIF ? 'gif' : 'webp';
                } elseif ($nativeInput !== null) {
                    $nativeAlpha = $nativeInput['alpha'];
                    $nativeIcc = $nativeInput['icc'];
                    $this->sourceHasAlpha = $nativeAlpha;
                } else {
                    [$nativeAlpha, $color] = $dimensions[2] === IMAGETYPE_PNG
                        ? ImageColor::fromPng($localPath)
                        : [false, ImageColor::fromJpeg($localPath, (int) ($dimensions['channels'] ?? 0))];
                    if ($color === ImageColor::UNSUPPORTED) {
                        return $this->originalImageUrl();
                    }
                    $nativeIcc = $color === ImageColor::ICC;
                    $this->sourceHasAlpha = $nativeAlpha;
                }
                $format = $this->params['format'] ?? $nativeAnimationFormat ?? $this->getSupportedImageFormat();
                if ($format === 'original') {
                    $format = $nativeAnimationFormat ?? $extension;
                }
                if (!in_array($format, $thumbnail === null ? ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'] : PhpThumbOptions::FORMATS, true)) {
                    return $this->originalImageUrl();
                }
                if ($animated && (!in_array($format, ['gif', 'webp'], true) ||
                    ($thumbnail !== null && $thumbnail->byteTarget() !== null))) {
                    return $this->originalImageUrl();
                }
                if ($nativeAnimationFormat !== null) {
                    $nativeTiming = ImageAnimation::timing($localPath, $dimensions[2]);
                    if ($nativeTiming === null) {
                        return $this->originalImageUrl();
                    }
                }

                $nativeOrientation = $nativeInput['orientation'] ?? ($dimensions[2] === IMAGETYPE_JPEG
                    ? NativeImage::readJpegOrientation($localPath) : 1);
                if ($thumbnail !== null) {
                    $nativeOrientation = $thumbnail->orientation($nativeOrientation);
                }

                // Build cache filename with transformation parameters
                $fit = $this->params['fit'] ?? null;
                $ext = $fit === null ? '' : $fit . '-';
                $crop = $this->params['crop'] ?? null;
                $ext .= $crop === null ? '' : strtolower($crop) . '-';
                $ext .= isset($this->params['focalCenterX']) ? 'focal' . $this->params['focalCenterX'] . '-' . $this->params['focalCenterY'] . '-' : '';
                if (isset($this->params['startX'], $this->params['startY'])) {
                    $ext .= 'manual' . $this->params['startX'] . '-' . $this->params['startY'] . '-';
                }
                $ext .= isset($this->params['w']) ? $this->params['w'] . 'x' . $this->params['h'] : '';
                $ext .= isset($this->params['sharpen']) && $this->params['sharpen'] > 0 ? '-sh' . $this->params['sharpen'] : '';
                $ext .= !empty($this->params['optimize']) ? '-opt-enc' : '';
                $ext .= $this->quality !== 100 ? '-q' . $this->quality : '';
                $ext .= $nativeOrientation > 1 ? '-exif' . $nativeOrientation : '';
                $ext .= $nativeAlpha ? '-alpha' : '';
                $ext .= $nativeIcc ? '-srgb' : '';
                $ext .= $nativeTiming !== null ? '-animated' : '';
                if ($this->qualityExplicit && in_array($format, ['webp', 'avif'], true) &&
                    ($this->quality === 100 || !empty($this->params['optimize']))) {
                    $ext .= '-fixed';
                }
                if ($nativeTiming !== null && $format === 'webp' && !$this->qualityExplicit && !empty($this->params['optimize'])) {
                    $ext .= '-adaptive';
                }
                $ext .= '-src' . substr(hash('sha256', $sourceFile . "\0" . json_encode($sourceVersion)), 0, 32);
                // Keep the native cache namespace used on 1.x.
                $ext .= '-native';
                if ($thumbnail !== null) {
                    $ext .= '-thumb' . substr(hash('sha256', json_encode([
                        $this->params['phpThumb'], $this->params['phpThumbBase'], $this->params['phpThumbAssets'],
                    ], JSON_THROW_ON_ERROR)), 0, 24);
                }
                if (isset($this->params['watermark'])) {
                    $sourceHash = hash_file('sha256', $localPath);
                    $cacheParams = $this->params;
                    $cacheParams['format'] = $format;
                    ksort($cacheParams);
                    $ext .= '-wm' . substr(hash('sha256', $sourceHash . json_encode($cacheParams) . $this->quality), 0, 24);
                }
                $suffix = (trim($ext) ? '-' . $ext : '') . '.' . $format;
                // Reserve space for pending/WebP candidates within the filesystem filename limit.
                $prefixLength = max(0, 220 - strlen($suffix));
                while ($prefixLength > 0 && isset($imageName[$prefixLength]) && (ord($imageName[$prefixLength]) & 0xc0) === 0x80) {
                    $prefixLength--;
                }
                $imageName = substr($imageName, 0, $prefixLength) . $suffix;

                if (str_contains($imageName, '/') || !GalleryFiles::relative($chacheFile . $imageName)) {
                    return sGalleryModel::NOIMAGE;
                }
                clearstatcache();
                $cacheDirectory = GalleryFiles::directory($chacheFile, true);
                if ($cacheDirectory === null) {
                    return $this->originalImageUrl();
                }
                $cachedPath = $cacheDirectory . DIRECTORY_SEPARATOR . $imageName;
                if (is_link($cachedPath)) {
                    return $this->originalImageUrl();
                }
                $cachedSize = is_file($cachedPath) ? filesize($cachedPath) : false;
                $animationType = $format === 'gif' ? IMAGETYPE_GIF : IMAGETYPE_WEBP;
                if ($cachedSize > 0 && ($format !== 'avif' || $this->isAvifFile($cachedPath)) &&
                    NativeImage::hasFormat($cachedPath, $format) &&
                    (!$animated || ImageAnimation::timing($cachedPath, $animationType) === $nativeTiming) &&
                    ImageSource::version($localPath) === $sourceVersion) {
                    return $this->derivativeUrl($sourceFile, $chacheFile . $imageName);
                }
                if ($format === 'avif' && (($this->params['format'] ?? null) !== 'original')) {
                    $fallbackName = preg_replace('/\.avif$/i', '.webp', $imageName);
                    $fallbackPath = $cacheDirectory . DIRECTORY_SEPARATOR . $fallbackName;
                    if (!is_link($fallbackPath) && is_file($fallbackPath) &&
                        (@getimagesize($fallbackPath)[2] ?? null) === IMAGETYPE_WEBP &&
                        ImageSource::version($localPath) === $sourceVersion) {
                        return $this->derivativeUrl($sourceFile, $chacheFile . $fallbackName);
                    }
                }
                if ($animated && !class_exists(\Imagick::class)) {
                    return $this->originalImageUrl();
                }
                // The cache is missing, empty or mislabeled.
                $this->optimizationJob = $this->optimizationDescriptor($format, $sourceVersion);
                $queue = new ImageOptimizationQueue();
                $fitsMemory = $thumbnail === null ? $this->fitsMemoryBudget($localPath, $dimensions, $nativeOrientation)
                    : $thumbnail->embeddedOnly() || ImageMemoryBudget::allows(
                        (float) $dimensions[0] * $dimensions[1] + $thumbnail->estimatedPixels($dimensions[0], $dimensions[1]),
                        (int) @filesize($localPath), $this->backgroundOptimization,
                    );
                if ($this->backgroundOptimization && !$fitsMemory) {
                    throw new \RuntimeException('Image optimization exceeds the worker memory budget.');
                }
                if (!$this->backgroundOptimization && (!$fitsMemory || $queue->heavy($this->optimizationJob))) {
                    $queue->defer($this->optimizationJob);
                    return $this->originalImageUrl();
                }
                $atomicSuffix = '.pending-' . bin2hex(random_bytes(6));
                $encodingPath = $cacheDirectory . DIRECTORY_SEPARATOR . $imageName . $atomicSuffix . '.' . $format;

                try {
                    $this->sourceHasAlpha = $nativeAlpha;
                    try {
                        if ($nativeTiming !== null) {
                            $this->renderNativeAnimation($sourceFile, $localPath, $dimensions, $nativeTiming, $format, $encodingPath, $thumbnail);
                        } else {
                            $this->renderWithDrivers(
                                $sourceFile, $format, $encodingPath, $nativeOrientation, $nativeAlpha, $nativeIcc, $nativeInputFormat, $thumbnail,
                            );
                        }
                    } catch (OptimizationDeferred $e) {
                        throw $e;
                    } catch (\Exception $e) {
                        if ($format !== 'avif' || ($this->params['format'] ?? '') === 'original') {
                            throw $e;
                        }
                        $format = 'webp';
                        $imageName = preg_replace('/\.avif$/i', '.webp', $imageName);
                        $encodingPath = $cacheDirectory . DIRECTORY_SEPARATOR . $imageName . $atomicSuffix . '.webp';
                        $this->renderWithDrivers(
                            $sourceFile, $format, $encodingPath, $nativeOrientation, $nativeAlpha, $nativeIcc, $nativeInputFormat, $thumbnail,
                        );
                    }
                    $this->assertSourceUnchanged($localPath, $sourceVersion);
                    $this->assertThumbnailAssetsUnchanged($thumbnail);
                    $publishedPath = $cacheDirectory . DIRECTORY_SEPARATOR . $imageName;
                    if (!chmod($encodingPath, 0644 & ~umask()) || !rename($encodingPath, $publishedPath)) {
                        throw new \RuntimeException('Cannot publish generated image.');
                    }
                    try {
                        $this->assertSourceUnchanged($localPath, $sourceVersion);
                        $this->assertThumbnailAssetsUnchanged($thumbnail);
                    } catch (\RuntimeException $error) {
                        if (is_file($publishedPath)) unlink($publishedPath);
                        throw $error;
                    }
                } catch (OptimizationDeferred $e) {
                    return $this->originalImageUrl();
                } catch (\Exception $e) {
                    if ($this->backgroundOptimization) throw $e;
                    return $this->originalImageUrl();
                } finally {
                    if (is_file($encodingPath)) unlink($encodingPath);
                }

                $finalPath = $cacheDirectory . DIRECTORY_SEPARATOR . $imageName;
                clearstatcache(true, $finalPath);
                if (is_link($finalPath) || !is_file($finalPath) || !filesize($finalPath) ||
                    ($format === 'avif' && !$this->isAvifFile($finalPath))) {
                    return sGalleryModel::NOIMAGE;
                }
                return $this->derivativeUrl($sourceFile, $chacheFile . $imageName);
            }

            $outputFile = $this->file;
            \Seiger\sGallery\Services\GalleryCache::remember($sourceFile, $outputFile);
            $this->file = $sourceFile;
            return GalleryFiles::url($outputFile);
        }

        return sGalleryModel::NOIMAGE;
    }

    /** Admit native resize and explicit output formats without requiring quality or another operation. @since 1.9.0 */
    private function shouldTransform(bool $animated): bool
    {
        $format = $this->params['format'] ?? null;
        return isset($this->params['phpThumb']) || $this->backgroundOptimization || $this->qualityExplicit || ($this->optimizationExplicit && !empty($this->params['optimize'])) ||
            isset($this->params['watermark']) || ($this->params['sharpen'] ?? 0) > 0 ||
            in_array($format, $animated ? ['gif', 'webp'] : ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true) ||
            isset($this->params['w'], $this->params['h']);
    }

    /** Identify crop requests that do not resize the source frame. @since 1.9.0 */
    private function hasCrop(): bool
    {
        return isset($this->params['crop']) || isset($this->params['focalCenterX']) || isset($this->params['startX']);
    }

    /**
     * Retry the full transform from the original when a driver cannot produce the requested format.
     * ICC conversions require Imagick. Each driver verifies its staged output before publication.
     * @since 1.9.0
     */
    private function renderWithDrivers(
        string $source,
        string $format,
        string $output,
        int $nativeOrientation,
        bool $nativeAlpha,
        bool $nativeIcc,
        ?string $nativeInputFormat,
        ?PhpThumbOptions $thumbnail,
    ): void
    {
        $drivers = $nativeIcc ? ['imagick'] : ['imagick', 'gd'];
        $drivers = array_filter($drivers, static fn (string $driver): bool => NativeImage::canEncode($format, $driver));
        $failure = new \RuntimeException('No image driver can produce the requested gallery format.');
        foreach ($drivers as $driver) {
            $this->checkOptimizationBudget();
            try {
                if ($thumbnail?->embeddedOnly()) {
                    $image = NativeImage::openExifThumbnail($source, $format, $driver);
                    $image->saveToPath($output, $this->quality);
                    return;
                }
                $image = NativeImage::open($source, $format, $driver);
                if ($nativeInputFormat !== null) {
                    $frame = $image->frame();
                    if (!$frame instanceof \Imagick) {
                        throw new \RuntimeException('The image source requires an Imagick decoder.');
                    }
                    $nativeAlpha = $frame->getImageAlphaChannel();
                    $nativeIcc = $frame->getImageProfiles('icc', false) !== [];
                    if (!$nativeIcc && !in_array($frame->getImageColorspace(), [\Imagick::COLORSPACE_SRGB, \Imagick::COLORSPACE_GRAY], true)) {
                        throw new \RuntimeException('The image source has unsupported color metadata.');
                    }
                    // JPEG XL decoding applies codestream orientation before exposing the frame.
                    $nativeOrientation = $nativeInputFormat === 'HEIC' ? $frame->getImageOrientation() : 1;
                }
                if ($nativeIcc) {
                    $image = $image->toSrgb();
                }
                $image = $image->orientFromExif($nativeOrientation);
                if ($nativeAlpha) {
                    $image = $image->withPixelAlpha();
                }
                $image = $thumbnail === null ? $this->transformNative($image, $format)
                    : $thumbnail->transform($image, $format, $this->params['phpThumbBase'], $this->checkOptimizationBudget(...));
                if ($thumbnail !== null && $format === 'gif') {
                    $image = $image->withGifTransparency();
                } elseif ($thumbnail === null && $nativeAlpha) {
                    $image = $image->withWhiteJpegBackground();
                    if ($format === 'gif') {
                        $image = $image->withGifTransparency();
                    }
                }
                if ($nativeIcc && in_array($format, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true)) {
                    $image->tagSrgb();
                }
                $image->optimize(!empty($this->params['optimize']));
                if ($thumbnail?->byteTarget() !== null) {
                    $this->saveThumbnailWithinTarget($image, $output, $format, $thumbnail->byteTarget());
                } elseif ($format === 'webp') {
                    $this->saveWebpImage($image, $output);
                } else {
                    $this->checkOptimizationBudget();
                    $image->saveToPath($output, $this->quality);
                }
                return;
            } catch (OptimizationDeferred $e) {
                throw $e;
            } catch (\Exception $e) {
                $failure = $e;
                if (is_file($output)) unlink($output);
            } finally {
                unset($image);
            }
        }

        throw $failure;
    }

    /**
     * Apply native geometry and sharpening before the watermark. An animation reuses the prepared overlay.
     * @param array{\GdImage|\Imagick, int, int}|null $overlay Resized watermark and position for the current render.
     * @since 1.9.0
     */
    private function transformNative(NativeImage $image, string $format, ?array &$overlay = null): NativeImage
    {
        if (isset($this->params['w'], $this->params['h'])) {
            [$width, $height] = [$this->params['w'], $this->params['h']];
            $image = match (true) {
                isset($this->params['fit']) => $image->fit($this->params['fit'], $width, $height),
                isset($this->params['crop']) => $image->crop($this->params['crop'], $width, $height),
                isset($this->params['focalCenterX'], $this->params['focalCenterY']) =>
                    $image->focalCrop($this->params['focalCenterX'], $this->params['focalCenterY'], $width, $height),
                isset($this->params['startX'], $this->params['startY']) =>
                    $image->manualCrop($this->params['startX'], $this->params['startY'], $width, $height),
                default => $image->resize($width, $height),
            };
        }

        if (!empty($this->params['sharpen'])) {
            $image = $image->sharpen($this->params['sharpen']);
        }
        $mark = $this->params['watermark'] ?? null;
        return $mark === null ? $image : Watermark::applyNative($image, $mark, $format, $overlay);
    }

    /**
     * Encode native animation candidates with the same WebP quality policy as still images.
     * Each candidate is verified before size selection. The Builder publishes only the final file.
     * @param array{loops: int, delays: list<int>} $timing Original container playback metadata.
     * @since 1.9.0
     */
    private function renderNativeAnimation(
        string $source, string $path, array $dimensions, array $timing, string $format, string $output,
        ?PhpThumbOptions $thumbnail,
    ): void {
        $overlay = null;
        $targetBytes = 0;
        $qualities = $format === 'webp' ? $this->getWebpOutputQualities() : [$this->quality];
        foreach ($qualities as $index => $quality) {
            NativeAnimation::render(
                $path, $dimensions, $timing, $format, $output, $quality,
                function (\Imagick $frame) use ($source, $format, $thumbnail, &$overlay, &$targetBytes): \Imagick {
                    $image = NativeImage::fromFrame($frame, $source, $format);
                    $image = $thumbnail === null ? $this->transformNative($image, $format, $overlay)
                        : $thumbnail->transform($image, $format, $this->params['phpThumbBase'], $this->checkOptimizationBudget(...));
                    $targetBytes = $this->getPageSpeedImageTargetBytes($image);
                    return $image->frame();
                },
                $this->checkOptimizationBudget(...),
                !empty($this->params['optimize']),
            );
            clearstatcache(true, $output);
            if (filesize($output) <= $targetBytes || $index === count($qualities) - 1) {
                return;
            }
            unlink($output);
        }
    }

    /** Keep the first candidate that meets maxb, or the smallest verified candidate. */
    private function saveThumbnailWithinTarget(NativeImage $image, string $output, string $format, int $target): void
    {
        $candidate = $output . '.candidate.' . $format;
        $smallest = PHP_INT_MAX;
        $palette = in_array($format, ['png', 'gif'], true);
        $qualities = [$this->quality];
        if (in_array($format, ['jpg', 'jpeg', 'webp', 'avif'], true)) {
            for ($quality = $this->quality - 10; $quality > 1; $quality -= 10) {
                $qualities[] = $quality;
            }
            if ($this->quality > 1) {
                $qualities[] = 1;
            }
        }
        try {
            $attempts = $palette ? [null, 128, 64, 32, 16, 8, 4, 2] : $qualities;
            foreach ($attempts as $attempt) {
                $this->checkOptimizationBudget();
                $frame = $palette && $attempt !== null ? $image->limitColors($attempt) : $image;
                $frame->saveToPath($candidate, $palette ? $this->quality : $attempt);
                clearstatcache(true, $candidate);
                $size = filesize($candidate);
                if ($size === false) {
                    throw new \RuntimeException('Cannot read the thumbnail byte-size candidate.');
                }
                if ($size < $smallest) {
                    if (!rename($candidate, $output)) {
                        throw new \RuntimeException('Cannot stage the thumbnail byte-size candidate.');
                    }
                    $smallest = $size;
                }
                if ($smallest <= $target) {
                    break;
                }
            }
        } finally {
            if (is_file($candidate)) {
                unlink($candidate);
            }
        }
    }

    private function thumbnailAssetsMatch(PhpThumbOptions $thumbnail): bool
    {
        try {
            return $thumbnail->assetRevision() === $this->params['phpThumbAssets'];
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function assertThumbnailAssetsUnchanged(?PhpThumbOptions $thumbnail): void
    {
        if ($thumbnail !== null && !$this->thumbnailAssetsMatch($thumbnail)) {
            throw new \RuntimeException('A thumbnail overlay changed during processing.');
        }
    }

    /** Register a generated or reused derivative before returning its URL. @since 1.9.0 */
    private function derivativeUrl(string $source, string $output): string
    {
        GalleryCache::remember($source, $output, true);
        return GalleryFiles::url($output);
    }

    /**
     * Build a JSON-stable scalar descriptor for the source and resolved output format.
     *
     * Source metadata separates replaced files from old pending jobs. Remote inputs
     * are not queued because their identity and availability cannot be verified here.
     * @param string $format Resolved cache format
     * @param array{int, int, string}|null $sourceVersion Revision already captured before reading image metadata.
     * @return array<string, mixed> Replayable descriptor, or an empty array for remote sources
     * @since 1.6.0
     */
    private function optimizationDescriptor(string $format, ?array $sourceVersion = null): array
    {
        if ($this->file === null || sGallery::hasLink($this->file)) {
            return [];
        }
        $params = $this->params ?? [];
        $params['format'] = $format;
        ksort($params);
        clearstatcache();
        return ImageOptimizationQueue::normalizeDescriptor(['file' => $this->file, 'params' => $params, 'quality' => $this->quality,
            'quality_explicit' => $this->qualityExplicit,
            'source_version' => $sourceVersion ?? ImageSource::version(EVO_BASE_PATH . $this->file)]);
    }

    /** Reject a replaced source at the publication boundary. @since 1.9.0 */
    private function assertSourceUnchanged(string $path, array $version): void
    {
        if (ImageSource::version($path) !== $version) {
            throw new \RuntimeException('Image source changed during optimization.');
        }
    }

    /** Return the untouched source URL while optimization is pending. @since 1.6.0 */
    private function originalImageUrl(): string
    {
        return GalleryFiles::url($this->file);
    }

    /**
     * Stop cooperative frontend processing after the package time budget.
     *
     * Slow markers are remembered even without sTask, so later requests avoid
     * repeating expensive attempts. Queue failures still return the original.
     * @return void
     * @since 1.6.0
     */
    private function checkOptimizationBudget(): void
    {
        if ($this->backgroundOptimization || microtime(true) - $this->optimizationStartedAt <
            sGallery::optimizationBudgetSeconds()) {
            return;
        }
        if ($this->optimizationJob !== []) {
            (new ImageOptimizationQueue())->defer($this->optimizationJob);
        }
        throw new OptimizationDeferred('Image optimization exceeded the frontend time budget.');
    }

    /**
     * Replay a deferred local transformation without the web deadline.
     *
     * Validate the dimensions, format and cached filename parameters before touching
     * the source. Earlier size/mtime descriptors pin the current contents after validation;
     * new descriptors also validate their queued hash. Only a published derivative counts as success.
     * @param array<string, mixed> $job Descriptor created by optimizationDescriptor()
     * @return string Published derived image URL
     * @since 1.6.0
     */
    public function processOptimization(array $job): string
    {
        $file = $job['file'] ?? null;
        $params = $job['params'] ?? [];
        if (!is_string($file) || !is_array($params) ||
            ((array_key_exists('w', $params) || array_key_exists('h', $params)) &&
                !self::validDimensions($params['w'] ?? null, $params['h'] ?? null)) ||
            !in_array($params['format'] ?? 'webp', isset($params['phpThumb']) ? PhpThumbOptions::FORMATS : self::OUTPUT_FORMATS, true)) {
            throw new \RuntimeException('Invalid gallery optimization parameters.');
        }
        if (isset($params['phpThumb']) && (!is_string($params['phpThumb']) ||
            !in_array($params['phpThumbBase'] ?? null, PhpThumbOptions::FORMATS, true) ||
            !is_string($params['phpThumbAssets'] ?? null))) {
            throw new \RuntimeException('Invalid gallery thumbnail job.');
        }
        foreach (['focalCenterX', 'focalCenterY', 'startX', 'startY'] as $coordinate) {
            if (array_key_exists($coordinate, $params) && (!is_int($params[$coordinate]) || $params[$coordinate] < 0)) {
                throw new \RuntimeException('Invalid gallery optimization coordinate.');
            }
        }
        $sharpen = $params['sharpen'] ?? 0;
        if ((!is_int($sharpen) && !is_float($sharpen)) || !is_finite($sharpen) || $sharpen < 0 || $sharpen > 100) {
            throw new \RuntimeException('Invalid gallery optimization sharpening value.');
        }
        $path = GalleryFiles::local($file);
        if ($path === null) {
            throw new \RuntimeException('Invalid local image optimization source.');
        }
        $sourceVersion = ImageSource::version($path);
        if (!ImageSource::matchesQueued($job['source_version'] ?? null, $sourceVersion)) {
            throw new \RuntimeException('Image optimization source changed while queued.');
        }
        $this->file($file);
        $this->params = $params;
        foreach (['fit' => NativeImage::FIT_MODES, 'crop' => array_keys(NativeImage::CROP_POSITIONS)] as $key => $values) {
            if (isset($params[$key]) && !in_array($params[$key], $values, true)) {
                throw new \RuntimeException('Invalid gallery optimization geometry.');
            }
        }
        if (isset($this->params['watermark'])) {
            $mark = $this->params['watermark'];
            if (!is_array($mark) || !is_string($mark['file'] ?? null) || !is_string($mark['hash'] ?? null) ||
                !is_array($mark['options'] ?? null)) {
                throw new \RuntimeException('Invalid gallery optimization watermark.');
            }
            // sTask sorts nested metadata. Restore the frontend order used by the cache hash.
            $this->params['watermark'] = ['file' => $mark['file'], 'hash' => $mark['hash'], 'options' => Watermark::options($mark['options'])];
        }
        $this->optimizationExplicit = !empty($this->params['optimize']);
        $this->quality = max(1, min(100, (int) ($job['quality'] ?? 100)));
        $this->qualityExplicit = (bool) ($job['quality_explicit'] ?? false);
        $this->backgroundOptimization = true;
        $this->queuedSourceVersion = $sourceVersion;
        $result = $this->getFile();
        $this->assertSourceUnchanged($path, $sourceVersion);
        if ($result === GalleryFiles::url($file) || $result === sGalleryModel::NOIMAGE) {
            throw new \RuntimeException('Image optimization did not publish a derived image.');
        }
        return $result;
    }

    /**
     * Determine and return the first supported image format based on browser capabilities.
     *
     * @return string
     */
    public function getSupportedImageFormat(): string
    {
        $availableFormats = $this->buildFormatPreferenceList();
        $autoJpeg = in_array('jpg', $availableFormats, true);
        $explicitFormats = $autoJpeg ? $availableFormats : [...$availableFormats, 'jpg'];

        if ($override = $this->getClientOverrideFormat($explicitFormats)) {
            return $this->rememberSupportedImageFormat($override);
        }

        if (isset($_SESSION['supported_image_format']) && in_array($_SESSION['supported_image_format'], $availableFormats, true)) {
            return $_SESSION['supported_image_format'];
        }

        $cookieFormat = $this->normalizeFormat($_COOKIE['sgallery_supported_image_format'] ?? null);
        if ($cookieFormat !== null && in_array($cookieFormat, $availableFormats, true)) {
            return $this->rememberSupportedImageFormat($cookieFormat);
        }

        $supportedFormat = $this->detectSupportedImageFormat($availableFormats);

        // A PNG fallback for this image should not replace the browser's preference.
        if (!$autoJpeg && $supportedFormat === 'png') {
            return $supportedFormat;
        }

        return $this->rememberSupportedImageFormat($supportedFormat);
    }

    /**
     * Set the file path for the builder.
     *
     * @param string $input Site path or public HTTP(S) image URL.
     * @return $this
     */
    public function file(string $input): self
    {
        if (str_starts_with($input, EVO_SITE_URL)) {
            $input = rawurldecode(substr($input, strlen(EVO_SITE_URL)));
        } elseif (!sGallery::hasLink($input)) {
            $base = rtrim(str_replace('\\', '/', EVO_BASE_PATH), '/') . '/';
            $input = str_replace('\\', '/', $input);
            if (str_starts_with($input, $base)) {
                $input = substr($input, strlen($base));
            } else {
                $input = ltrim($input, '/');
            }
        }
        $this->resetBuilder();
        $this->file = $input;
        return $this;
    }

    /**
     * Get the rendered gallery view.
     *
     * @return string Rendered view of the gallery.
     */
    public function getView(): string
    {
        $sGalleryController = new sGalleryController($this->viewType, $this->itemType, $this->idType, $this->blockName);
        $view = $sGalleryController->index();
        return $view instanceof View ? $view->render() : (string)$view;
    }

    /**
     * Render the view or return the processed file path as a string when treated like a string.
     *
     * @return string Rendered view or processed file path.
     */
    public function __toString(): string
    {
        try {
            $result = $this->file !== null ? $this->getFile() : $this->getView();
            $this->resetBuilder();
            return $result;
        } catch (HttpExceptionInterface $error) {
            $this->resetBuilder();
            return '';
        } catch (\Exception $e) {
            return "Error sGallery: " . $e->getMessage();
        }
    }

    /**
     * Initialize the query if not already done and the mode is 'collections'.
     */
    protected function initQuery(): void
    {
        if (is_null($this->query) && $this->mode === 'collections') {
            $this->query = sGalleryModel::query();
        }
    }

    /**
     * Check and return the first supported image format from a list.
     *
     * @param array $formats List of formats to check.
     * @return string First supported format or 'jpg' by default.
     */
    protected function detectSupportedImageFormat(array $formats): string
    {
        if (empty($formats)) {
            return 'jpg';
        }

        if ($format = $this->detectSupportedFormatFromHeaders($formats)) {
            return $format;
        }

        if ($format = $this->guessSupportedFormatByUserAgent($formats)) {
            return $format;
        }

        $hasAlpha = $this->sourceHasAlphaChannel();

        if ($hasAlpha && in_array('png', $formats, true)) {
            return 'png';
        }

        if (!$hasAlpha && in_array('jpg', $formats, true)) {
            return 'jpg';
        }

        return reset($formats) ?: 'jpg';
    }

    /**
     * Build preferred format list based on source properties.
     *
     * @return array
     */
    private function buildFormatPreferenceList(): array
    {
        return $this->sourceHasAlphaChannel() ? ['webp', 'png'] : ['webp', 'jpg', 'png'];
    }

    /**
     * Check the image type in the file contents before publishing or serving an AVIF derivative.
     *
     * @param string $path Local path to a newly encoded or cached derivative.
     * @return bool Whether PHP identifies the image as AVIF.
     * @since 1.9.0
     */
    private function isAvifFile(string $path): bool
    {
        $info = @getimagesize($path);
        return ($info[2] ?? null) === IMAGETYPE_AVIF;
    }

    /**
     * Reset builder state for the next usage.
     *
     * @return void
     */
    protected function resetBuilder(): void
    {
        $this->query = null;
        $this->files = null;
        $this->file = null;
        $this->params = [];
        $this->type = null;
        $this->sourceHasAlpha = null;
        $this->qualityExplicit = false;
        $this->optimizationExplicit = false;
        $this->quality = (int) config('seiger.settings.sGallery.quality', 100);
        $this->params['sharpen'] = (float) config('seiger.settings.sGallery.sharpen', 0);
        $this->params['optimize'] = (bool) config('seiger.settings.sGallery.optimize', true);
        $defaultFormat = config('seiger.settings.sGallery.format', 'auto');
        if ($defaultFormat !== 'auto') $this->format($defaultFormat);
        $this->alphaDetectionCache = [];
        $this->backgroundOptimization = false;
        $this->optimizationStartedAt = 0;
        $this->optimizationJob = [];
        $this->queuedSourceVersion = null;
        $this->metadataSourceVersion = null;
    }

    /**
     * Get the effective WebP output quality.
     *
     * Keep the configured default below 100 and honor an explicit quality of 100.
     *
     * @return int
     * @since 1.5.0
     */
    private function getWebpOutputQuality(): int
    {
        if ($this->qualityExplicit) {
            return $this->quality;
        }
        return $this->quality >= 100 ? 99 : $this->quality;
    }

    /** Use one quality for fixed requests, otherwise keep the existing descending WebP search. @since 1.9.0 */
    private function getWebpOutputQualities(): array
    {
        return $this->qualityExplicit || empty($this->params['optimize'])
            ? [$this->getWebpOutputQuality()]
            : [99, 97, 95, 93, 92, 91, 90, 89, 88, 87, 86, 85, 84, 82, 80];
    }

    /**
     * Save the image as WebP with optional adaptive PageSpeed-friendly quality.
     *
     * Explicit quality settings keep the requested value. Adaptive output retains
     * all 15 quality candidates; only its final nonempty file is atomically published.
     * Front requests check their deadline between attempts and return the original
     * if work is deferred. Background jobs complete the full quality search.
     *
     * @param NativeImage $image The resized image used for WebP candidates.
     * @param string $path The path where to save the WebP image.
     * @return void
     * @since 1.5.0
     */
    private function saveWebpImage(NativeImage $image, string $path): void
    {
        $targetBytes = $this->getPageSpeedImageTargetBytes($image);
        $qualities = $this->getWebpOutputQualities();
        $temporaryPath = $path . '.webp';
        try {
            foreach ($qualities as $index => $quality) {
                $this->checkOptimizationBudget();
                $image->saveToPath($temporaryPath, $quality);
                clearstatcache();
                if (!is_file($temporaryPath) || filesize($temporaryPath) === 0) {
                    throw new \RuntimeException('WebP generation produced an empty file.');
                }
                if (filesize($temporaryPath) <= $targetBytes || $index === count($qualities) - 1) {
                    break;
                }
            }
            if (!rename($temporaryPath, $path)) {
                throw new \RuntimeException('Unable to publish the generated WebP cache file.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * Estimate the target file size for PageSpeed image delivery checks.
     *
     * The estimate is based on rendered pixels and the Lighthouse/WebPageTest-style
     * bytes-per-pixel target used for modern image delivery recommendations.
     *
     * @param NativeImage $image The frame whose dimensions determine the size target.
     * @return int Estimated target size in bytes.
     * @since 1.5.0
     */
    private function getPageSpeedImageTargetBytes(NativeImage $image): int
    {
        [$width, $height] = $image->dimensions();
        $pixels = max(1, $width * $height);
        $displaySafetyFactor = 0.9;
        $targetBytesPerPixel = 2 / 12;

        return (int) ceil($pixels * $displaySafetyFactor * $targetBytesPerPixel) + 4096;
    }

    /** Include the source, output and intermediate resize in the estimate. @since 1.9.0 */
    private function fitsMemoryBudget(string $path, array $dimensions, int $nativeOrientation): bool
    {
        [$width, $height] = $dimensions;
        if ($nativeOrientation >= 5) {
            [$width, $height] = [$height, $width];
        }
        $sourcePixels = (float) $width * $height * ($nativeOrientation > 1 ? 2 : 1);
        $targetWidth = $this->params['w'] ?? $width;
        $targetHeight = $this->params['h'] ?? $height;
        $outputPixels = (float) $targetWidth * $targetHeight;
        $fit = $this->params['fit'] ?? null;
        if ($fit === null && $this->hasCrop()) {
            $outputPixels = (float) min($width, $targetWidth) * min($height, $targetHeight);
        } elseif ($fit === null && isset($this->params['w'])) {
            // A width- or height-driven resize can exceed the requested rectangle.
            $aspect = $width / $height;
            $outputPixels = max(
                $outputPixels,
                $targetWidth * max(1, ceil($targetWidth / $aspect)),
                $targetHeight * max(1, ceil($targetHeight * $aspect)),
            );
        }

        $bytes = (int) @filesize($path);
        $watermark = $this->params['watermark'] ?? null;
        if ($watermark !== null) {
            try {
                $markPath = Watermark::path($watermark['file']);
            } catch (\InvalidArgumentException) {
                $markPath = null;
            }
            $markDimensions = $markPath === null ? false : @getimagesize($markPath);
            if ($markDimensions !== false && self::validDimensions($markDimensions[0], $markDimensions[1])) {
                [$markWidth, $markHeight] = $markDimensions;
                $markOption = (string) $watermark['options']['width'];
                $scaledWidth = str_ends_with($markOption, '%')
                    ? max($width, $height, $targetWidth, $targetHeight) * ((int) $markOption / 100)
                    : (int) $markOption;
                $outputPixels += (float) $markWidth * $markHeight + $scaledWidth * ceil($scaledWidth * $markHeight / $markWidth);
                $bytes += (int) @filesize($markPath);
            }
        }

        return ImageMemoryBudget::allows($sourcePixels + $outputPixels, $bytes, $this->backgroundOptimization);
    }

    /**
     * Determine if current source image includes an alpha channel.
     *
     * @return bool
     */
    private function sourceHasAlphaChannel(): bool
    {
        if ($this->sourceHasAlpha !== null) {
            return $this->sourceHasAlpha;
        }

        $absolutePath = $this->getAbsoluteSourcePath();
        if ($absolutePath !== null) {
            $this->sourceHasAlpha = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'png'
                ? ImageColor::fromPng($absolutePath)[0]
                : $this->detectAlphaChannelFromPath($absolutePath);
            return $this->sourceHasAlpha;
        }

        if ($this->file !== null) {
            $extension = strtolower(pathinfo($this->file, PATHINFO_EXTENSION));
            $this->sourceHasAlpha = in_array($extension, ['png', 'gif', 'webp'], true);
            return $this->sourceHasAlpha;
        }

        $this->sourceHasAlpha = false;
        return false;
    }

    /**
     * Resolve absolute path for current or given relative file.
     *
     * @param string|null $relative Site-relative local path
     * @return string|null
     */
    private function getAbsoluteSourcePath(?string $relative = null): ?string
    {
        $target = $relative ?? $this->file;

        if ($target === null || sGallery::hasLink($target)) {
            return null;
        }

        return GalleryFiles::local($target) ?? GalleryFiles::local('assets/sgallery/' . $target);
    }

    /**
     * Detect whether image at path uses transparency.
     *
     * @param string $path
     * @return bool
     */
    private function detectAlphaChannelFromPath(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $stats = @stat($path);
        $cacheKey = (realpath($path) ?: $path) . '|' . ($stats['size'] ?? 0) . '|' . ($stats['mtime'] ?? 0);

        if (array_key_exists($cacheKey, $this->alphaDetectionCache)) {
            return $this->alphaDetectionCache[$cacheKey];
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $result = false;

        switch ($extension) {
            case 'png':
                if (!\function_exists('imagecreatefrompng')) {
                    break;
                }
                $resource = @imagecreatefrompng($path);
                if ($resource) {
                    \imagealphablending($resource, true);
                    \imagesavealpha($resource, true);
                    $result = $this->hasTransparentPixel($resource);
                    unset($resource);
                }
                break;
            case 'gif':
                if (!\function_exists('imagecreatefromgif')) {
                    break;
                }
                $resource = @imagecreatefromgif($path);
                if (!$resource) {
                    break;
                }
                if ($resource) {
                    $transparentIndex = \imagecolortransparent($resource);
                    if ($transparentIndex >= 0) {
                        $result = true;
                    } else {
                        $result = $this->hasTransparentPixel($resource);
                    }
                    unset($resource);
                }
                break;
            case 'webp':
                if (\function_exists('imagecreatefromwebp')) {
                    $resource = @imagecreatefromwebp($path);
                    if ($resource) {
                        $result = $this->hasTransparentPixel($resource);
                        unset($resource);
                    }
                }
                break;
            default:
                $result = false;
        }

        $this->alphaDetectionCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Inspect GD resource for presence of transparent pixels.
     *
     * @param \GdImage|resource $resource
     * @return bool
     */
    private function hasTransparentPixel($resource): bool
    {
        if (!\is_resource($resource) && !($resource instanceof \GdImage)) {
            return false;
        }

        $width = \imagesx($resource);
        $height = \imagesy($resource);

        if ($width === 0 || $height === 0) {
            return false;
        }

        $sampleWidth = min($width, 100);
        $sampleHeight = min($height, 100);
        $stepX = max(1, (int)floor($width / $sampleWidth));
        $stepY = max(1, (int)floor($height / $sampleHeight));

        for ($x = 0; $x < $width; $x += $stepX) {
            for ($y = 0; $y < $height; $y += $stepY) {
                $color = \imagecolorat($resource, $x, $y);
                $alpha = ($color >> 24) & 0x7F;
                if ($alpha > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Try to fetch preferred format provided by client.
     *
     * @param array $availableFormats
     * @return string|null
     */
    private function getClientOverrideFormat(array $availableFormats): ?string
    {
        $sources = [
            $_GET['sgallery-format'] ?? null,
            $_POST['sgallery-format'] ?? null,
            $_REQUEST['sgallery-format'] ?? null,
        ];

        foreach ($sources as $value) {
            $normalized = $this->normalizeFormat($value);
            if ($normalized !== null && in_array($normalized, $availableFormats, true)) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Normalize common format aliases.
     *
     * @param mixed $value
     * @return string|null
     */
    private function normalizeFormat(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $format = strtolower(trim($value));

        if ($format === 'jpeg') {
            $format = 'jpg';
        }

        return in_array($format, ['avif', 'webp', 'png', 'jpg', 'gif'], true) ? $format : null;
    }

    /**
     * Store supported format in session and cookie.
     *
     * @param string $format
     * @return string
     */
    private function rememberSupportedImageFormat(string $format): string
    {
        $_SESSION['supported_image_format'] = $format;

        $currentCookie = $this->normalizeFormat($_COOKIE['sgallery_supported_image_format'] ?? null);

        if ($currentCookie !== $format && !headers_sent()) {
            $cookieOptions = [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'samesite' => 'Lax',
            ];

            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $cookieOptions['secure'] = true;
            }

            setcookie('sgallery_supported_image_format', $format, $cookieOptions);
        }

        return $format;
    }

    /**
     * Detect format support based on request headers.
     *
     * @param array $formats
     * @return string|null
     */
    private function detectSupportedFormatFromHeaders(array $formats): ?string
    {
        $headers = [];

        foreach (['HTTP_SEC_CH_ACCEPT', 'HTTP_ACCEPT'] as $headerName) {
            if (!empty($_SERVER[$headerName])) {
                $headers[] = strtolower($_SERVER[$headerName]);
            }
        }

        if (empty($headers)) {
            return null;
        }

        foreach ($headers as $header) {
            foreach ($formats as $format) {
                foreach ($this->mapFormatToMimeList($format) as $mime) {
                    if (strpos($header, $mime) !== false) {
                        return $format === 'jpeg' ? 'jpg' : $format;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Provide MIME candidates for specific format.
     *
     * @param string $format
     * @return array
     */
    private function mapFormatToMimeList(string $format): array
    {
        return match ($format) {
            'avif' => ['image/avif'],
            'webp' => ['image/webp'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg', 'image/jpg'],
            default => ['image/' . $format],
        };
    }

    /**
     * Guess supported format using User-Agent heuristics.
     *
     * @param array $formats
     * @return string|null
     */
    private function guessSupportedFormatByUserAgent(array $formats): ?string
    {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

        if ($userAgent === '') {
            return null;
        }

        $supportsAvif = false;
        $supportsWebp = false;

        $chromeVersion = $this->extractVersionFromUserAgent($userAgent, ['chrome/', 'crios/', 'crmo/']);
        if ($chromeVersion !== null) {
            if ($chromeVersion >= 85.0) {
                $supportsAvif = true;
            }
            if ($chromeVersion >= 32.0) {
                $supportsWebp = true;
            }
        }

        $edgeVersion = $this->extractVersionFromUserAgent($userAgent, ['edg/', 'edgios/']);
        if ($edgeVersion !== null) {
            if ($edgeVersion >= 85.0) {
                $supportsAvif = true;
            }
            if ($edgeVersion >= 18.0) {
                $supportsWebp = true;
            }
        }

        $operaVersion = $this->extractVersionFromUserAgent($userAgent, ['opr/', 'opera/']);
        if ($operaVersion !== null) {
            if ($operaVersion >= 71.0) {
                $supportsAvif = true;
            }
            if ($operaVersion >= 19.0) {
                $supportsWebp = true;
            }
        }

        $firefoxVersion = $this->extractVersionFromUserAgent($userAgent, ['firefox/', 'fxios/']);
        if ($firefoxVersion !== null) {
            if ($firefoxVersion >= 93.0) {
                $supportsAvif = true;
            }
            if ($firefoxVersion >= 65.0) {
                $supportsWebp = true;
            }
        }

        if ($this->isSafariUserAgent($userAgent)) {
            $safariVersion = $this->extractVersionFromUserAgent($userAgent, ['version/']);
            $iosVersion = $this->extractIosVersion($userAgent);

            if ($safariVersion !== null) {
                if ($safariVersion >= 16.0) {
                    $supportsAvif = true;
                }
                if ($safariVersion >= 14.0) {
                    $supportsWebp = true;
                }
            } elseif ($iosVersion !== null) {
                if ($iosVersion >= 16.0) {
                    $supportsAvif = true;
                }
                if ($iosVersion >= 14.0) {
                    $supportsWebp = true;
                }
            }
        }

        if (strpos($userAgent, 'samsungbrowser/') !== false) {
            $samsungVersion = $this->extractVersionFromUserAgent($userAgent, ['samsungbrowser/']);
            if ($samsungVersion !== null) {
                if ($samsungVersion >= 14.0) {
                    $supportsAvif = true;
                }
                if ($samsungVersion >= 5.0) {
                    $supportsWebp = true;
                }
            }
        }

        foreach ($formats as $format) {
            if ($format === 'avif' && $supportsAvif) {
                return 'avif';
            }

            if ($format === 'webp' && $supportsWebp) {
                return 'webp';
            }

            if ($format === 'png') {
                return 'png';
            }

            if (in_array($format, ['jpg', 'jpeg'], true)) {
                return 'jpg';
            }
        }

        return null;
    }

    /**
     * Extract numeric version from User-Agent markers.
     *
     * @param string $userAgent
     * @param array $markers
     * @return float|null
     */
    private function extractVersionFromUserAgent(string $userAgent, array $markers): ?float
    {
        foreach ($markers as $marker) {
            $position = strpos($userAgent, $marker);
            if ($position === false) {
                continue;
            }

            $versionString = substr($userAgent, $position + strlen($marker));
            if ($versionString === false) {
                continue;
            }

            if (preg_match('/^([0-9]+(?:\.[0-9]+)?)/', $versionString, $matches)) {
                return (float)$matches[1];
            }
        }

        return null;
    }

    /**
     * Determine if User-Agent represents Safari (desktop or iOS).
     *
     * @param string $userAgent
     * @return bool
     */
    private function isSafariUserAgent(string $userAgent): bool
    {
        if (strpos($userAgent, 'safari') === false) {
            return false;
        }

        $nonSafariMarkers = ['chrome', 'crios', 'crmo', 'fxios', 'ucbrowser', 'edg', 'opr/', 'opera', 'yabrowser'];
        foreach ($nonSafariMarkers as $marker) {
            if (strpos($userAgent, $marker) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract iOS version from User-Agent string.
     *
     * @param string $userAgent
     * @return float|null
     */
    private function extractIosVersion(string $userAgent): ?float
    {
        if (preg_match('/os\s(\d+)[._](\d+)/', $userAgent, $matches)) {
            return (float)($matches[1] . '.' . $matches[2]);
        }

        return null;
    }
}
