<?php namespace Seiger\sGallery\Builders;

use Seiger\sGallery\Controllers\sGalleryController;
use Seiger\sGallery\Models\sGalleryModel;

/**
 * Class sGalleryBuilder
 *
 * This builder class provides a fluent interface to configure and retrieve gallery data.
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
     * Set the item type.
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
     * Set resize parameters.
     *
     * @param int $width
     * @param int $height
     * @return self
     */
    public function resize(int $width, int $height): self
    {
        $this->params['w'] = $width;
        $this->params['h'] = $height;
        return $this;
    }

    /**
     * Get the URL of the file.
     *
     * @param string $filePath
     * @return string
     */
    protected function getFileUrl(string $filePath): string
    {
        if (file_exists($filePath)) {
            return str_replace(MODX_BASE_PATH, MODX_SITE_URL, $filePath);
        }

        return sGalleryModel::NOIMAGE;
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
     * Get the URL of the file.
     *
     * @return string
     */
    public function getFile(): string
    {
        if ($this->file !== null) {
            return $this->getFileUrl($this->file);
        }

        return sGalleryModel::NOIMAGE;
    }

    /**
     * Set the file path.
     *
     * @param string $input
     * @return self
     */
    public function file(string $input): self
    {
        $this->file = $input;
        return $this;
    }

    /**
     * Get the URL of the file.
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
     * Render the view or return the file path as a string when the object is treated like a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            if ($this->file !== null) {
                return $this->getFile();
            }

            return $this->getView();
        } catch (\Exception $e) {
            return "Error sGallery: " . $e->getMessage();
        }
    }
}
