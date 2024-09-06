<?php namespace Seiger\sGallery\Builders;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Seiger\sGallery\Controllers\sGalleryController;
use Seiger\sGallery\Models\sGalleryModel;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

/**
 * Class sGalleryBuilder
 *
 * This builder class provides a fluent interface to configure and retrieve gallery data.
 * It supports view configuration, file manipulation (including resizing and format conversion),
 * and browser-based format detection.
 */
class sGalleryBuilder
{
    protected string $mode = 'view';
    protected string $viewType = sGalleryModel::VIEW_SECTION;
    protected string $itemType = 'resource';
    protected string $idType = 'i';
    protected ?string $blockName = '1';
    protected ?int $documentId = null;
    protected int $quality = 80;
    protected ?string $lang = null;
    protected ?\Illuminate\Database\Eloquent\Builder $query = null;
    protected ?\Illuminate\Database\Eloquent\Collection $files = null;
    protected ?string $file = null;
    protected ?array $params = [];

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
     * Set the format of the image output.
     *
     * @param string $format Image format (e.g., 'jpg', 'webp').
     * @return $this
     */
    public function format(string $format): self
    {
        $this->params['format'] = strtolower($format);
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
        $this->quality = $quality;
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
        $height = $height ?: $width;
        $this->params['w'] = max(1, $width);
        $this->params['h'] = max(1, $height);
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
        $height = $height ?: $width;

        $fitMethods = [
            'crop' => Fit::Crop,
            'contain' => Fit::Contain,
            'max' => Fit::Max,
            'fill' => Fit::Fill,
            'stretch' => Fit::Stretch,
            'fill-max' => Fit::FillMax,
        ];

        $this->params['fit'] = $fitMethods[$method] ?? Fit::Crop;
        $this->params['w'] = max(1, $width);
        $this->params['h'] = max(1, $height);

        return $this;
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
     * @return string URL of the processed image or 'no image' placeholder.
     */
    public function getFile(): string
    {
        if ($this->file !== null && file_exists(MODX_BASE_PATH . $this->file)) {
            $extension = strtolower(pathinfo(MODX_BASE_PATH . $this->file, PATHINFO_EXTENSION));

            if ($this->params !== null && !in_array($extension, ['svg'])) {
                $imageName = str_replace('.' . pathinfo($this->file, PATHINFO_EXTENSION), '', pathinfo($this->file, PATHINFO_BASENAME));
                $imagePath = explode(DIRECTORY_SEPARATOR, pathinfo($this->file, PATHINFO_DIRNAME));
                $chacheFile = sGalleryModel::CACHE_DIR;

                foreach ($imagePath as $path) {
                    $chacheFile .= is_numeric($path) ? $path : $path[0];
                }

                $chacheFile .= DIRECTORY_SEPARATOR;
                $format = $this->params['format'] ?? $this->getSupportedImageFormat();

                $ext = isset($this->params['fit']) ? $this->params['fit']->value . '-' : '';
                $ext .= isset($this->params['w']) ? $this->params['w'] . 'x' . $this->params['h'] : '';
                $imageName .= (trim($ext) ? '-' . $ext : '') . '.' . $format;

                if (!file_exists(MODX_BASE_PATH . $chacheFile . $imageName)) {
                    if (!file_exists(MODX_BASE_PATH . $chacheFile)) {
                        mkdir(MODX_BASE_PATH . $chacheFile, octdec(evo()->getConfig('new_folder_permissions', '0777')), true);
                        chmod(MODX_BASE_PATH . $chacheFile, octdec(evo()->getConfig('new_folder_permissions', '0777')));
                    }

                    try {
                        $image = extension_loaded('imagick')
                            ? Image::load(MODX_BASE_PATH . $this->file)
                            : Image::useImageDriver(ImageDriver::Gd)->loadFile(MODX_BASE_PATH . $this->file);

                        if (isset($this->params['fit']) && isset($this->params['w']) && isset($this->params['h'])) {
                            $image->fit($this->params['fit'], $this->params['w'], $this->params['h']);
                        } elseif (isset($this->params['w']) && isset($this->params['h'])) {
                            $image->width($this->params['w'])->height($this->params['h']);
                        }

                        $image->quality($this->quality)->format($format)->save(MODX_BASE_PATH . $chacheFile . $imageName);
                        chmod(MODX_BASE_PATH . $chacheFile . $imageName, octdec(evo()->getConfig('new_file_permissions', '0666')));
                    } catch (\Exception $e) {
                        Log::error("Error sGallery: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                        return "Error sGallery: " . $e->getMessage();
                    }
                }

                $this->file = $chacheFile . $imageName;
            }

            return MODX_SITE_URL . $this->file;
        }

        return sGalleryModel::NOIMAGE;
    }

    /**
     * Determine and return the first supported image format based on browser capabilities.
     *
     * @return string
     */
    public function getSupportedImageFormat(): string
    {
        if (isset($_SESSION['supported_image_format'])) {
            return $_SESSION['supported_image_format'];
        }

        $supportedFormat = $this->detectSupportedImageFormat(['avif', 'webp', 'jpg']);
        $_SESSION['supported_image_format'] = $supportedFormat;

        return $supportedFormat;
    }

    /**
     * Set the file path for the builder.
     *
     * @param string $input File path relative to the base path.
     * @return $this
     */
    public function file(string $input): self
    {
        $input = trim(str_replace([MODX_SITE_URL, MODX_BASE_PATH], '', $input), '/');
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
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

        foreach ($formats as $format) {
            if (strpos($acceptHeader, 'image/' . $format) !== false) {
                return $format;
            }
        }

        return 'jpg';
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
    }
}
