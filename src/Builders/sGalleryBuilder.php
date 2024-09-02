<?php namespace Seiger\sGallery\Builders;

use Seiger\sGallery\Models\sGalleryModel;
use Illuminate\Support\Facades\View;

/**
 * Class sGalleryBuilder
 *
 * This builder class provides a fluent interface to configure and retrieve gallery data.
 */
class sGalleryBuilder
{
    protected string $viewType = 'default';
    protected string $resourceType = 'resource';
    protected int|null $documentId = null;
    protected string|null $lang = null;
    protected string|null $blockName = null;
    protected array $resizeParams = [];

    /**
     * Set the view type.
     *
     * @param string $viewType
     * @return self
     */
    public function viewType(string $viewType): self
    {
        $this->viewType = $viewType;
        return $this;
    }

    /**
     * Set the resource type.
     *
     * @param string $resourceType
     * @return self
     */
    public function resourceType(string $resourceType): self
    {
        $this->resourceType = $resourceType;
        return $this;
    }

    /**
     * Set the document ID.
     *
     * @param int $documentId
     * @return self
     */
    public function idType(int $documentId): self
    {
        $this->documentId = $documentId;
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
     * @return object|View
     */
    public function initialise()
    {
        // Determine document ID if not set
        if (is_null($this->documentId)) {
            $this->documentId = evo()->documentObject['id'] ?? 0;
        }

        // Determine language if not set
        if (is_null($this->lang)) {
            $this->lang = evo()->getConfig('lang', 'base');
        }

        // Build query to the sGalleryModel
        $query = sGalleryModel::lang($this->lang)
            ->whereParent($this->documentId)
            ->whereResourceType($this->resourceType);

        if (!is_null($this->blockName) && trim($this->blockName) !== '') {
            $query->whereBlock($this->blockName);
        }

        // Get the result
        $galleries = $query->orderBy('position')->get();

        // You may want to return a View or another object
        // For example:
        // return View::make('sgallery::view.' . $this->viewType, ['galleries' => $galleries]);

        // For example purposes, return the collection
        return $galleries;
    }

    /**
     * Perform image resizing based on the set parameters.
     *
     * @param string $input
     * @return string
     */
    public function resize(string $input): string
    {
        // Use methods from the sGallery class or other logic to resize
        // For example, using the resizeImage method from sGallery

        // If you need to use an instance of sGallery, you may need to inject it or use a static method
        // For simplicity, let's assume we're calling a static method

        return \Seiger\sGallery\sGallery::initialise()->resizeImage($input, $this->resizeParams);
    }

    // Additional methods can be added as needed
}
