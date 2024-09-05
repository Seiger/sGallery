<?php namespace Seiger\sGallery\Builders;

use Illuminate\Support\Facades\Log;
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
    protected string $viewType = sGalleryModel::VIEW_SECTION;
    protected string $itemType = 'resource';
    protected string $idType = 'i';
    protected string $blockName = '1';
    protected ?string $file = null;
    protected ?array $params = [];

    /**
     * Set the view type.
     *
     * @param string $viewType
     * @return self
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
     * @param string $itemType
     * @return self
     */
    public function itemType(string $itemType): self
    {
        $this->itemType = $itemType;
        return $this;
    }

    /**
     * Set the document ID type.
     *
     * @param string $idType
     * @return self
     */
    public function idType(string $idType): self
    {
        $this->idType = $idType;
        return $this;
    }

    /**
     * Set the block name.
     *
     * @param string $blockName
     * @return self
     */
    public function blockName(string $blockName): self
    {
        $this->blockName = $blockName;
        return $this;
    }

    /**
     * Set the format of the image output.
     *
     * @param string $format
     * @return self
     */
    public function format(string $format): self
    {
        $this->params['format'] = strtolower($format);
        return $this;
    }

    /**
     * Set the resize dimensions for the image.
     *
     * @param int $width
     * @param int|null $height
     * @return self
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
     * @param string $method
     * @param int $width
     * @param int|null $height
     * @return self
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
     * Initialize and retrieve the gallery view.
     *
     * @return self
     */
    public function initialise(): self
    {
        return $this;
    }

    /**
     * Get the URL of the processed file (resized, formatted).
     *
     * @return string
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

                $imageName .= '-' . (isset($this->params['fit']) ? $this->params['fit']->value . '-' : '');
                $imageName .= isset($this->params['w']) ? $this->params['w'] . 'x' . $this->params['h'] : '';
                $imageName .= '.' . $format;

                if (!file_exists(MODX_BASE_PATH . $chacheFile . $imageName)) {
                    if (!file_exists(MODX_BASE_PATH . $chacheFile)) {
                        mkdir(MODX_BASE_PATH . $chacheFile, octdec(evo()->getConfig('new_folder_permissions', '0777')), true);
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

                        $image->format($format)->save(MODX_BASE_PATH . $chacheFile . $imageName);
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
     * @param string $input
     * @return self
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
     * @return string
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
     * @return string
     */
    public function __toString(): string
    {
        try {
            return $this->file !== null ? $this->getFile() : $this->getView();
        } catch (\Exception $e) {
            return "Error sGallery: " . $e->getMessage();
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
}
