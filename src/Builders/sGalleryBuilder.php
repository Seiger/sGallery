<?php namespace Seiger\sGallery\Builders;

use Seiger\sGallery\Controllers\sGalleryController;
use Seiger\sGallery\Models\sGalleryModel;
use Illuminate\Support\Facades\View;

/**
 * Class sGalleryBuilder
 *
 * This builder class provides a fluent interface to configure and retrieve gallery data.
 */
class sGalleryBuilder
{
    protected string $viewType = sGalleryModel::VIEW_TAB;
    protected string $itemType = 'resource';
    protected string $idType = 'id';
    protected string $blockName = '1';
    protected int|null $documentId = null;
    protected string|null $lang = null;
    protected array $resizeParams = [];

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
     * Set the resource type.
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
     * Set the language.
     *
     * @param string $lang
     * @return self
     */
    public function language(string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    /**
     * Set parameters for resizing images.
     *
     * @param array $params
     * @return self
     */
    public function resizeParams(array $params): self
    {
        $this->resizeParams = $params;
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
     * Render the view as a string when the object is treated like a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            $sGalleryController = new sGalleryController($this->viewType, $this->itemType, $this->idType, $this->blockName);
            return $sGalleryController->index(); // Assuming this returns a View object
        } catch (\Exception $e) {
            // Handle any exceptions and return an error message as a string
            return "Error initializing gallery: " . $e->getMessage();
        }
    }
}
