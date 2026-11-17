<?php namespace Seiger\sGallery\Builders;

use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Seiger\sGallery\Controllers\sGalleryController;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\sGallery;
use Seiger\sGallery\Exceptions\OptimizationDeferred;
use Seiger\sGallery\Services\GalleryFiles;
use Seiger\sGallery\Services\GalleryMedia;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Spatie\Image\Enums\CropPosition;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

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
    private const MAX_IMAGE_SIDE = 8192;
    private const MAX_IMAGE_PIXELS = 24000000;
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

    /**
     * Cached AVIF support driver (null = not checked, 'imagick' = ImageMagick supports AVIF, 'gd' = GD supports AVIF, false = no support)
     *
     * @var string|false|null
     */
    protected static $avifSupportDriver = null;

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
     * Configure an explicit or default watermark; invocation alone enables it.
     * @param string|array|null $file Local image path, options, or default configuration
     * @param array $options Position, width, opacity and padding overrides
     * @return $this
     * @since 1.7.0
     */
    public function watermark(string|array|null $file = null, array $options = []): self
    {
        if (!class_exists(Image::class)) {
            return $this;
        }
        if (is_array($file)) { $options = $file; $file = null; }
        $defaults = (array) config('seiger.settings.sGallery.watermark', []);
        $file = $file ?? ($defaults['file'] ?? '');
        unset($this->params['watermark']);
        if ($file === '') return $this;
        $path = \Seiger\sGallery\Services\Watermark::path($file);
        unset($defaults['file']);
        $this->params['watermark'] = ['file' => $file, 'hash' => hash_file('sha256', $path), 'options' => \Seiger\sGallery\Services\Watermark::options(array_replace($defaults, $options))];
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
     * Set the quality for the image output.
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
     * Run Spatie image optimizer after the processed image is saved.
     *
     * @param bool $optimize Whether to optimize the output image.
     * @return $this
     */
    public function optimize(bool $optimize = true): self
    {
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
        if (!class_exists(Image::class)) {
            return $this->resize($width, $height);
        }
        $this->setDimensions($width, $height ?: $width);

        $fitMethods = [
            'crop' => Fit::Crop,
            'contain' => Fit::Contain,
            'max' => Fit::Max,
            'fill' => Fit::Fill,
            'stretch' => Fit::Stretch,
            'fill-max' => Fit::FillMax,
        ];

        $this->params['fit'] = $fitMethods[$method] ?? Fit::Crop;

        return $this;
    }

    /**
     * Set the crop method and dimensions for the image.
     *
     * @param string $method Fit method (e.g., 'center', 'left', 'right').
     * @param int $width Width of the image.
     * @param int|null $height Height of the image (optional, defaults to the width if not provided).
     * @return $this
     */
    public function crop(string $method, int $width, int|null $height = null): self
    {
        if (!class_exists(Image::class)) {
            return $this->resize($width, $height);
        }
        $this->setDimensions($width, $height ?: $width);

        $cropMethods = [
            'topLeft' => CropPosition::TopLeft,
            'top' => CropPosition::Top,
            'topRight' => CropPosition::TopRight,
            'left' => CropPosition::Left,
            'center' => CropPosition::Center,
            'right' => CropPosition::Right,
            'bottomLeft' => CropPosition::BottomLeft,
            'bottom' => CropPosition::Bottom,
            'bottomRight' => CropPosition::BottomRight,
        ];

        $this->params['crop'] = $cropMethods[$method] ?? CropPosition::Center;
        return $this;
    }

    /**
     * Set focal crop with percentage-based center point.
     *
     * @param int $centerX The X coordinate of the focal center (0-100)
     * @param int $centerY The Y coordinate of the focal center (0-100)
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

    /** Set output dimensions after validating the combined pixel count. @since 1.8.0 */
    private function setDimensions(int $width, int $height): void
    {
        $width = max(1, $width);
        $height = max(1, $height);
        if (!self::withinImageLimits($width, $height)) {
            throw new \InvalidArgumentException('Gallery image dimensions exceed the allowed size.');
        }
        $this->params['w'] = $width;
        $this->params['h'] = $height;
    }

    /** Check dimensions using division so the pixel count cannot overflow. @since 1.8.0 */
    private static function withinImageLimits(mixed $width, mixed $height): bool
    {
        return is_int($width) && is_int($height) && $width > 0 && $height > 0 &&
            $width <= self::MAX_IMAGE_SIDE && $height <= self::MAX_IMAGE_SIDE &&
            $width <= intdiv(self::MAX_IMAGE_PIXELS, $height);
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

    /**
     * Get the URL of the processed file (resized, formatted).
     *
     * Slow web transformations return the original and optionally enqueue a versioned
     * sTask job. Empty cache files are regenerated. The cooperative deadline cannot
     * interrupt a native encoder already executing. Remote URLs are returned to
     * the browser without fetching them. Local images must fit the decode limits.
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
            if (!class_exists(Image::class)) {
                return $this->originalImageUrl();
            }
            $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

            if ($this->params !== null && !in_array($extension, ['svg'])) {
                if (!$this->isProcessableImage($localPath)) {
                    return sGalleryModel::NOIMAGE;
                }

                $imageName = str_replace('.' . pathinfo($this->file, PATHINFO_EXTENSION), '', pathinfo($this->file, PATHINFO_BASENAME));
                $imagePath = explode('/', str_replace('\\', '/', pathinfo($this->file, PATHINFO_DIRNAME)));
                $chacheFile = sGalleryModel::imageCacheDir();

                foreach ($imagePath as $path) {
                    if ($path !== '.') {
                        $chacheFile .= is_numeric($path) ? $path : ($path[0] ?? '');
                    }
                }

                $chacheFile .= '/';
                $format = $this->params['format'] ?? $this->getSupportedImageFormat();
                if ($format === 'original') {
                    $format = $extension;
                    if (!in_array($format, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) throw new \InvalidArgumentException('Original image format is not supported by the processing driver.');
                }

                // Ensure format is supported (if AVIF was requested but not supported, fallback to WebP)
                if ($format === 'avif' && !$this->isAvifSupported()) {
                    if (($this->params['format'] ?? '') === 'original') throw new \RuntimeException('AVIF encoding is unavailable for the original format.');
                    $format = 'webp';
                }

                // Build cache filename with transformation parameters
                $ext = isset($this->params['fit']) ? strtolower($this->params['fit']->value) . '-' : '';
                $ext .= isset($this->params['crop']) ? strtolower($this->params['crop']->value) . '-' : '';
                $ext .= isset($this->params['focalCenterX']) ? 'focal' . $this->params['focalCenterX'] . '-' . $this->params['focalCenterY'] . '-' : '';
                $ext .= isset($this->params['w']) ? $this->params['w'] . 'x' . $this->params['h'] : '';
                $ext .= isset($this->params['sharpen']) && $this->params['sharpen'] > 0 ? '-sh' . $this->params['sharpen'] : '';
                $ext .= !empty($this->params['optimize']) ? '-opt' : '';
                $ext .= $this->quality !== 100 ? '-q' . $this->quality : '';
                if (isset($this->params['watermark'])) {
                    $sourceHash = hash_file('sha256', $localPath);
                    $cacheParams = $this->params;
                    $cacheParams['format'] = $format;
                    ksort($cacheParams);
                    $ext .= '-wm' . substr(hash('sha256', $sourceHash . json_encode($cacheParams) . $this->quality), 0, 24);
                }
                $imageName .= (trim($ext) ? '-' . $ext : '') . '.' . $format;

                if (str_contains($imageName, '/') || !GalleryFiles::relative($chacheFile . $imageName)) {
                    return sGalleryModel::NOIMAGE;
                }
                clearstatcache();
                $cacheDirectory = GalleryFiles::directory($chacheFile, true);
                if ($cacheDirectory === null) {
                    return sGalleryModel::NOIMAGE;
                }
                $cachedPath = $cacheDirectory . DIRECTORY_SEPARATOR . $imageName;
                if (is_link($cachedPath)) {
                    return sGalleryModel::NOIMAGE;
                }
                $cachedSize = is_file($cachedPath) ? filesize($cachedPath) : false;
                if ($cachedSize > 0 && ($format !== 'avif' || $this->isAvifFile($cachedPath))) {
                    $output = $chacheFile . $imageName;
                    \Seiger\sGallery\Services\GalleryCache::remember($sourceFile, $output, true);
                    return GalleryFiles::url($output);
                }
                if ($format === 'avif') {
                    $fallbackName = preg_replace('/\.avif$/i', '.webp', $imageName);
                    $fallbackPath = $cacheDirectory . DIRECTORY_SEPARATOR . $fallbackName;
                    if (!is_link($fallbackPath) && is_file($fallbackPath) && filesize($fallbackPath) > 100) {
                        $output = $chacheFile . $fallbackName;
                        \Seiger\sGallery\Services\GalleryCache::remember($sourceFile, $output, true);
                        return GalleryFiles::url($output);
                    }
                }
                // The cache is missing, empty or mislabeled.
                $this->optimizationJob = $this->optimizationDescriptor($format);
                if (!$this->backgroundOptimization && $this->optimizationJob !== [] &&
                    (new ImageOptimizationQueue())->heavy($this->optimizationJob)) {
                    (new ImageOptimizationQueue())->defer($this->optimizationJob);
                    return $this->originalImageUrl();
                }
                $encodingPath = null;

                try {
                    $file = $localPath;
                    $this->sourceHasAlpha = $this->detectAlphaChannelFromPath($file);
                    // Use appropriate driver based on format and AVIF support
                    if ($format === 'avif') {
                        // Ensure AVIF support is checked and driver is determined
                        if (self::$avifSupportDriver === null) {
                            $this->isAvifSupported();
                        }

                        // Use the driver that supports AVIF
                        if (self::$avifSupportDriver === 'imagick') {
                            $image = Image::useImageDriver(ImageDriver::Imagick)->loadFile($file);
                        } elseif (self::$avifSupportDriver === 'gd') {
                            $image = Image::useImageDriver(ImageDriver::Gd)->loadFile($file);
                        } else {
                            // AVIF not supported, fallback to WebP
                            $format = 'webp';
                            $imageName = preg_replace('/\.avif$/i', '.webp', $imageName);
                            $image = Image::load($file);
                        }
                    } else {
                        $image = Image::load($file);
                    }

                    // Apply image transformations based on parameters
                    if (isset($this->params['fit']) && isset($this->params['w']) && isset($this->params['h'])) {
                        $image->fit($this->params['fit'], $this->params['w'], $this->params['h']);
                    } elseif (isset($this->params['crop']) && isset($this->params['w']) && isset($this->params['h'])) {
                        $image->crop($this->params['w'], $this->params['h'], $this->params['crop']);
                    } elseif (isset($this->params['focalCenterX']) && isset($this->params['focalCenterY']) && isset($this->params['w']) && isset($this->params['h'])) {
                        $image->focalCrop($this->params['w'], $this->params['h'], $this->params['focalCenterX'], $this->params['focalCenterY']);
                    } elseif (isset($this->params['startX']) && isset($this->params['startY']) && isset($this->params['w']) && isset($this->params['h'])) {
                        $image->manualCrop($this->params['w'], $this->params['h'], $this->params['startX'], $this->params['startY']);
                    } elseif (isset($this->params['w']) && isset($this->params['h'])) {
                        $image->width($this->params['w'])->height($this->params['h']);
                    }

                    if (isset($this->params['sharpen']) && $this->params['sharpen'] > 0) {
                        $image->sharpen($this->params['sharpen']);
                    }

                    if (isset($this->params['watermark'])) {
                        \Seiger\sGallery\Services\Watermark::apply($image, $this->params['watermark']);
                    }

                    if (!empty($this->params['optimize'])) {
                        $image->optimize();
                    }

                    $this->checkOptimizationBudget();

                    $atomicSuffix = '.pending-' . bin2hex(random_bytes(6));
                    $encodingPath = $cacheDirectory . DIRECTORY_SEPARATOR . $imageName . $atomicSuffix . '.' . $format;
                    // Handle AVIF format with custom transparency support
                    if ($format === 'avif') {
                        $image->quality($this->quality);

                        // Check if original file has transparency (PNG, GIF, WebP)
                        $originalExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $hasTransparency = in_array($originalExtension, ['png', 'gif', 'webp']);

                        $avifPath = $encodingPath;
                        try {
                            if ($hasTransparency) {
                                $this->saveAvifWithTransparency($image, $avifPath);
                            } else {
                                $image->format($format)->save($avifPath);
                            }

                            if (!$this->isAvifFile($avifPath)) {
                                throw new \RuntimeException('Encoder did not produce an AVIF image.');
                            }
                        } catch (\Exception $e) {
                            if (is_file($avifPath)) {
                                @unlink($avifPath);
                            }
                            if (($this->params['format'] ?? '') === 'original') throw $e;
                            Log::warning("AVIF creation failed, falling back to WebP: " . $e->getMessage());

                            $format = 'webp';
                            $imageName = preg_replace('/\.avif$/i', '.webp', $imageName);
                            $encodingPath = $cacheDirectory . DIRECTORY_SEPARATOR . $imageName . $atomicSuffix;
                            $this->saveWebpImage($image, $encodingPath);
                        }
                    } else {
                        if ($format === 'webp') {
                            $this->saveWebpImage($image, $encodingPath);
                        } else {
                            $image->quality($this->quality)->format($format)->save($encodingPath);
                        }
                    }
                    if ($atomicSuffix !== '') {
                        if (!is_file($encodingPath) || !getimagesize($encodingPath) || !rename($encodingPath, $cacheDirectory . DIRECTORY_SEPARATOR . $imageName)) {
                            throw new \RuntimeException('Cannot publish generated image.');
                        }
                    }
                    chmod($cacheDirectory . DIRECTORY_SEPARATOR . $imageName, 0644 & ~umask());
                } catch (OptimizationDeferred $e) {
                    return $this->originalImageUrl();
                } catch (\Exception $e) {
                    Log::error("Error sGallery: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                } finally {
                    if (isset($atomicSuffix) && $atomicSuffix !== '' && $encodingPath && is_file($encodingPath)) unlink($encodingPath);
                }

                $finalPath = $cacheDirectory . DIRECTORY_SEPARATOR . $imageName;
                clearstatcache(true, $finalPath);
                if (is_link($finalPath) || !is_file($finalPath) || !filesize($finalPath) ||
                    ($format === 'avif' && !$this->isAvifFile($finalPath))) {
                    return sGalleryModel::NOIMAGE;
                }
                $output = $chacheFile . $imageName;
                \Seiger\sGallery\Services\GalleryCache::remember($sourceFile, $output, true);
                return GalleryFiles::url($output);
            }

            $outputFile = $this->file;
            \Seiger\sGallery\Services\GalleryCache::remember($sourceFile, $outputFile);
            $this->file = $sourceFile;
            return GalleryFiles::url($outputFile);
        }

        return sGalleryModel::NOIMAGE;
    }

    /**
     * Build a scalar descriptor for a local source and the resolved output format.
     *
     * Source metadata separates replaced files from old pending jobs. Remote inputs
     * are not queued because their identity and availability cannot be verified here.
     * @param string $format Resolved cache format
     * @return array<string, mixed> Replayable descriptor, or an empty array for remote sources
     * @since 1.6.0
     */
    private function optimizationDescriptor(string $format): array
    {
        if ($this->file === null || sGallery::hasLink($this->file)) {
            return [];
        }
        $params = $this->params ?? [];
        $params['format'] = $format;
        foreach ($params as &$value) {
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
        }
        unset($value);
        ksort($params);
        clearstatcache();
        return ['file' => $this->file, 'params' => $params, 'quality' => $this->quality,
            'quality_explicit' => $this->qualityExplicit,
            'source_version' => [@filesize(EVO_BASE_PATH . $this->file), @filemtime(EVO_BASE_PATH . $this->file)]];
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
     * the source. Only a published derivative counts as a completed task.
     * @param array<string, mixed> $job Descriptor created by optimizationDescriptor()
     * @return string Published derived image URL
     * @since 1.6.0
     */
    public function processOptimization(array $job): string
    {
        if (!class_exists(Image::class)) {
            throw new \RuntimeException('Image optimization requires the optional spatie/image package (^3).');
        }
        $file = $job['file'] ?? null;
        $params = $job['params'] ?? [];
        if (!is_string($file) || !is_array($params) ||
            ((array_key_exists('w', $params) || array_key_exists('h', $params)) &&
                !self::withinImageLimits($params['w'] ?? null, $params['h'] ?? null)) ||
            !in_array($params['format'] ?? 'webp', self::OUTPUT_FORMATS, true)) {
            throw new \RuntimeException('Invalid gallery optimization parameters.');
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
        clearstatcache();
        if (($job['source_version'] ?? null) !== [filesize($path), filemtime($path)]) {
            throw new \RuntimeException('Image optimization source changed while queued.');
        }
        $this->file($file);
        $this->params = $params;
        foreach (['fit' => Fit::class, 'crop' => CropPosition::class] as $key => $enum) {
            if (isset($this->params[$key])) {
                $this->params[$key] = $enum::from($this->params[$key]);
            }
        }
        $this->quality = max(1, min(100, (int) ($job['quality'] ?? 100)));
        $this->qualityExplicit = (bool) ($job['quality_explicit'] ?? false);
        $this->backgroundOptimization = true;
        $result = $this->getFile();
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

        if ($override = $this->getClientOverrideFormat($availableFormats)) {
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
        $hasAlpha = $this->sourceHasAlphaChannel();

        $formats = [];
        $formats[] = 'webp';

        if ($hasAlpha) {
            $formats[] = 'png';
            $formats[] = 'jpg';
        } else {
            $formats[] = 'jpg';
            $formats[] = 'png';
        }

        return array_values(array_unique($formats));
    }

    /**
     * Select and cache the first driver that can encode a real AVIF image.
     *
     * @return bool Whether Imagick or GD produced an AVIF probe.
     */
    protected function isAvifSupported(): bool
    {
        if (self::$avifSupportDriver !== null) {
            return self::$avifSupportDriver !== false;
        }

        if (extension_loaded('imagick')) {
            try {
                $probe = new \Imagick();
                $probe->newImage(1, 1, 'black');
                $probe->setImageFormat('AVIF');
                $blob = $probe->getImageBlob();
                $info = $blob !== '' ? @getimagesizefromstring($blob) : false;
                if (($info[2] ?? null) === IMAGETYPE_AVIF) {
                    self::$avifSupportDriver = 'imagick';
                    return true;
                }
            } catch (\Exception $e) {
                // A decode-only ImageMagick build may list AVIF but reject encoding.
            }
        }

        if (function_exists('imageavif') && function_exists('imagetypes') && (imagetypes() & IMG_AVIF) !== 0) {
            $stream = tmpfile();
            if ($stream !== false) {
                try {
                    $probe = imagecreatetruecolor(1, 1);
                    if ($probe !== false && @imageavif($probe, $stream) && rewind($stream)) {
                        $blob = stream_get_contents($stream);
                        $info = $blob !== false ? @getimagesizefromstring($blob) : false;
                        if (($info[2] ?? null) === IMAGETYPE_AVIF) {
                            self::$avifSupportDriver = 'gd';
                            return true;
                        }
                    }
                } finally {
                    fclose($stream);
                }
            }
        }

        self::$avifSupportDriver = false;
        return false;
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
        $this->quality = (int) config('seiger.settings.sGallery.quality', 100);
        $this->params['sharpen'] = (float) config('seiger.settings.sGallery.sharpen', 0);
        $this->params['optimize'] = (bool) config('seiger.settings.sGallery.optimize', true);
        $defaultFormat = config('seiger.settings.sGallery.format', 'auto');
        if ($defaultFormat !== 'auto') $this->format($defaultFormat);
        $this->alphaDetectionCache = [];
        $this->backgroundOptimization = false;
        $this->optimizationStartedAt = 0;
        $this->optimizationJob = [];
    }

    /**
     * Get the effective WebP output quality.
     *
     * WebP quality 100 can create unnecessarily heavy files, so the default
     * upper bound is normalized to 99 unless a lower quality is configured.
     *
     * @return int
     * @since 1.5.0
     */
    private function getWebpOutputQuality(): int
    {
        return $this->quality >= 100 ? 99 : $this->quality;
    }

    /**
     * Save the image as WebP with optional adaptive PageSpeed-friendly quality.
     *
     * Explicit quality settings keep the requested value. Adaptive output retains
     * all 15 quality candidates; only its final nonempty file is atomically published.
     * Front requests check their deadline between attempts and return the original
     * if work is deferred. Background jobs complete the full quality search.
     *
     * @param \Spatie\Image\Image $image The image instance.
     * @param string $path The path where to save the WebP image.
     * @return void
     * @since 1.5.0
     */
    private function saveWebpImage(Image $image, string $path): void
    {
        $targetBytes = $this->getPageSpeedImageTargetBytes($image);
        $qualities = $this->qualityExplicit || empty($this->params['optimize'])
            ? [$this->getWebpOutputQuality()]
            : [99, 97, 95, 93, 92, 91, 90, 89, 88, 87, 86, 85, 84, 82, 80];
        $temporaryPath = tempnam(dirname($path), '.sgallery-webp-');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Unable to create a temporary WebP cache file.');
        }
        try {
            foreach ($qualities as $index => $quality) {
                $this->checkOptimizationBudget();
                $image->quality($quality)->format('webp')->save($temporaryPath);
                clearstatcache();
                if (!is_file($temporaryPath) || filesize($temporaryPath) === 0) {
                    throw new \RuntimeException('WebP generation produced an empty file.');
                }
                if (filesize($temporaryPath) <= $targetBytes || $index === count($qualities) - 1) {
                    break;
                }
            }
            clearstatcache();
            if ($this->optimizationJob !== [] && $this->optimizationJob['source_version'] !==
                [@filesize(EVO_BASE_PATH . $this->optimizationJob['file']), @filemtime(EVO_BASE_PATH . $this->optimizationJob['file'])]) {
                throw new \RuntimeException('Image source changed during optimization.');
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
     * @param \Spatie\Image\Image $image The image instance.
     * @return int Estimated target size in bytes.
     * @since 1.5.0
     */
    private function getPageSpeedImageTargetBytes(Image $image): int
    {
        $pixels = max(1, $image->getWidth() * $image->getHeight());
        $displaySafetyFactor = 0.9;
        $targetBytesPerPixel = 2 / 12;

        return (int) ceil($pixels * $displaySafetyFactor * $targetBytesPerPixel) + 4096;
    }

    /**
     * Save image as AVIF with proper transparency support.
     *
     * @param \Spatie\Image\Image $image The image instance.
     * @param string $path The path where to save the image.
     * @return void
     */
    private function saveAvifWithTransparency($image, string $path): void
    {
        $originalFile = $this->file;
        $driverName = $image->driverName();
        $imageResource = $image->image();

        // If using ImageMagick, we can save directly without custom transparency handling
        if ($driverName === 'imagick') {
            $image->format('avif')->save($path);
            return;
        }

        // For GD driver, use custom transparency handling
        $gdImage = $imageResource;

        // Check if image has alpha channel
        $width = \imagesx($gdImage);
        $height = \imagesy($gdImage);
        $isTrueColor = \imageistruecolor($gdImage);
        $hasAlpha = false;

        if ($isTrueColor) {
            for ($x = 0; $x < min($width, 10); $x++) {
                for ($y = 0; $y < min($height, 10); $y++) {
                    $color = \imagecolorat($gdImage, $x, $y);
                    $alpha = ($color >> 24) & 0xFF;
                    if ($alpha < 255) {
                        $hasAlpha = true;
                        break 2;
                    }
                }
            }
        }

        // Check if original file has transparency before attempting custom approach
        $originalHasTransparency = false;
        $absoluteOriginalPath = $this->getAbsoluteSourcePath($originalFile);
        if ($absoluteOriginalPath !== null) {
            $originalHasTransparency = $this->detectAlphaChannelFromPath($absoluteOriginalPath);
        }

        // If Spatie Image lost transparency and original has transparency, try to reload original image directly
        if (!isset($this->params['watermark']) && !$hasAlpha && $originalHasTransparency && $absoluteOriginalPath !== null) {
            $originalPath = $absoluteOriginalPath;
            $originalImage = null;

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION));
            switch ($extension) {
                case 'png':
                    // For PNG, load without modifying alpha settings initially
                    $originalImage = \imagecreatefrompng($originalPath);
                    break;
                case 'jpg':
                case 'jpeg':
                    $originalImage = \imagecreatefromjpeg($originalPath);
                    break;
                case 'gif':
                    $originalImage = \imagecreatefromgif($originalPath);
                    break;
                case 'webp':
                    $originalImage = \imagecreatefromwebp($originalPath);
                    break;
            }

            if ($originalImage) {
                $originalWidth = \imagesx($originalImage);
                $originalHeight = \imagesy($originalImage);

                // For PNG, apply alpha settings first, then check
                if ($extension === 'png') {
                    \imagepalettetotruecolor($originalImage);
                    \imagealphablending($originalImage, false);
                    \imagesavealpha($originalImage, true);
                }

                // Check if original image has transparency
                $originalHasAlpha = false;

                // Check pixels for transparency detection
                for ($x = 0; $x < min($originalWidth, 50); $x++) {
                    for ($y = 0; $y < min($originalHeight, 50); $y++) {
                        $color = \imagecolorat($originalImage, $x, $y);
                        $alpha = ($color >> 24) & 0xFF;

                        if ($alpha < 255) { // Any transparency
                            $originalHasAlpha = true;
                            break 2;
                        }
                    }
                }

                $targetWidth = $width;
                $targetHeight = $height;
                $resizedImage = \imagecreatetruecolor($targetWidth, $targetHeight);

                // Preserve transparency - set transparent background
                \imagealphablending($resizedImage, false);
                \imagesavealpha($resizedImage, true);

                // Fill with transparent color
                $transparent = \imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
                \imagefill($resizedImage, 0, 0, $transparent);

                // Copy and resize with better transparency handling
                \imagealphablending($resizedImage, true);
                \imagecopyresampled(
                    $resizedImage, $originalImage,
                    0, 0, 0, 0,
                    $targetWidth, $targetHeight,
                    \imagesx($originalImage), \imagesy($originalImage)
                );
                \imagealphablending($resizedImage, false);

                unset($originalImage, $gdImage);
                $gdImage = $resizedImage;
            }
        }

        // Apply transparency fixes
        \imagepalettetotruecolor($gdImage);
        \imagealphablending($gdImage, false);
        \imagesavealpha($gdImage, true);

        $quality = $this->quality;
        if ($originalHasTransparency || $hasAlpha) {
            $this->markTransparentSource();
            if (!$this->qualityExplicit) {
                $quality = 100;
            }
        } else {
            $this->clearTransparentSource();
        }

        // Check if AVIF encoding is actually supported before attempting
        if (!function_exists('imageavif')) {
            unset($gdImage);
            throw new \RuntimeException('imageavif() function is not available');
        }

        // Suppress warnings but check return value
        $result = @\imageavif($gdImage, $path, $quality);
        unset($gdImage);

        // Check if file was created and has content
        if ($result === false || !file_exists($path) || filesize($path) < 100) {
            // Clean up failed file
            if (file_exists($path)) {
                @unlink($path);
            }
            throw new \RuntimeException('AVIF encoding failed or resulted in empty file');
        }
    }

    /**
     * Validate if file contains processable image data.
     *
     * @param string $filePath
     * @return bool
     */
    private function isProcessableImage(string $filePath): bool
    {
        $dimensions = @getimagesize($filePath);
        if ($dimensions === false && class_exists(\Imagick::class) &&
            in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['heic', 'heif'], true)) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($filePath);
            if (in_array($mime, ['image/heic', 'image/heif'], true)) {
                try {
                    $metadata = new \Imagick();
                    $metadata->pingImage($filePath);
                    $dimensions = [$metadata->getImageWidth(), $metadata->getImageHeight()];
                } catch (\ImagickException $error) {
                    return false;
                }
            }
        }
        return $dimensions !== false && self::withinImageLimits($dimensions[0], $dimensions[1]);
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
            $this->sourceHasAlpha = $this->detectAlphaChannelFromPath($absolutePath);
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
     * Persistently mark current source as containing transparency.
     *
     * @return void
     */
    private function markTransparentSource(): void
    {
        $this->sourceHasAlpha = true;
    }

    /**
     * Persistently mark current source as opaque.
     *
     * @return void
     */
    private function clearTransparentSource(): void
    {
        $this->sourceHasAlpha = false;
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
