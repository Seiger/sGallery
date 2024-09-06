<?php namespace Seiger\sGallery;

use Illuminate\Support\Str;
use Illuminate\View\View;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Controllers\sGalleryController;
use Seiger\sGallery\Models\sGalleryModel;
use Spatie\Image\Enums\Fit;

/**
 * Class sGallery
 *
 * This class provides methods to work with galleries, including checking if a type is an image or video,
 * initializing a gallery, retrieving galleries, resizing images, and more.
 */
class sGallery
{
    protected const DEFAULT_WIDTH = 240;
    protected const DEFAULT_HEIGHT = 120;
    protected const DEFAULT_FIT = 'crop';
    protected sGalleryBuilder $builder;

    public function __construct()
    {
        $this->builder = new sGalleryBuilder();
    }

    /**
     * Initialize the view builder (default mode).
     *
     * @return sGalleryBuilder The builder instance for chaining further operations.
     */
    public function initialiseView(): sGalleryBuilder
    {
        return $this->builder->setMode('view');
    }

    /**
     * Initialize the collections query builder.
     *
     * @return sGalleryBuilder The builder instance for retrieving collections.
     */
    public function collections(): sGalleryBuilder
    {
        return $this->builder->setMode('collections');
    }

    /**
     * Return the URL of the file using the Builder pattern.
     *
     * @param string $input The path of the input file.
     * @return sGalleryBuilder The builder instance to chain further operations.
     */
    public function file(string $input): sGalleryBuilder
    {
        return $this->builder->file($input);
    }

    /**
     * Initialize the Gallery with the specified parameters.
     *
     * @param string $viewType The type of view (default is 'tab').
     * @param string $resourceType The type of resource (default is 'resource').
     * @param string $idType The type of id (default is 'id').
     * @param string $blockName The block name (default is '1').
     * @return View|string The initialized view or an error message.
     * @deprecated Use sGallery::initialiseView() instead.
     */
    public function initialise(string $viewType = '', string $resourceType = 'resource', string $idType = 'id', string $blockName = '1')
    {
        trigger_error('Method initialise() is deprecated. Use the sGallery::initialiseView() instead.', E_USER_DEPRECATED);

        $viewTypeDef = sGalleryModel::VIEW_TAB;
        if (in_array($viewType, [
            sGalleryModel::VIEW_TAB,
            sGalleryModel::VIEW_SECTION,
            sGalleryModel::VIEW_SECTION_DOWNLOADS
        ])) {
            $viewTypeDef = $viewType;
        }
        $viewType = $viewTypeDef;

        try {
            $sGalleryController = new sGalleryController($viewType, $resourceType, $idType, $blockName);
            return $sGalleryController->index();
        } catch (\Exception $e) {
            return "Error initializing gallery: " . $e->getMessage();
        }
    }

    /**
     * Retrieve all gallery items for a specific resource type and document.
     *
     * @param string $resourceType Resource type (default is 'resource').
     * @param int|null $documentId Document ID (optional).
     * @param string|null $lang Language code (optional).
     * @return object Collection of gallery items.
     */
    public function all(string $resourceType = 'resource', int $documentId = null, string $lang = null): object
    {
        if (!$documentId) {
            $documentId = evo()->documentObject['id'] ?? 0;
        }

        if (!$lang) {
            $lang = evo()->getConfig('lang', 'base');
        }

        return sGalleryModel::lang($lang)
            ->whereParent($documentId)
            ->whereItemType($resourceType)
            ->orderBy('position')
            ->get();
    }

    /**
     * Retrieve gallery items for a specific block and resource type.
     *
     * @param string $blockName Name of the block (default is '1').
     * @param string $resourceType Resource type (default is 'resource').
     * @param int|null $documentId Document ID (optional).
     * @param string|null $lang Language code (optional).
     * @return object Collection of gallery items for the specified block.
     * @deprecated Use sGallery::collections()->blockName('photo')->get() instead.
     */
    public function block(string $blockName = '1', string $resourceType = 'resource', int $documentId = null, string $lang = null): object
    {
        trigger_error('Method block() is deprecated. Use the sGallery::collections()->blockName("photo")->get() instead.', E_USER_DEPRECATED);

        if (!$documentId) {
            $documentId = evo()->documentObject['id'] ?? 0;
        }

        if (!$lang) {
            $lang = evo()->getConfig('lang', 'base');
        }

        return sGalleryModel::lang($lang)
            ->whereParent($documentId)
            ->whereBlock($blockName)
            ->whereItemType($resourceType)
            ->orderBy('position')
            ->get();
    }

    /**
     * Retrieve the first gallery item for a specific resource type and document.
     *
     * @param string $resourceType Resource type (default is 'resource').
     * @param int|null $documentId Document ID (optional).
     * @param string|null $lang Language code (optional).
     * @param string|null $block Block name for filtering (optional).
     * @return object The first gallery item or a new instance if none found.
     */
    public function first(string $resourceType = 'resource', int $documentId = null, string $lang = null, string $block = null): object
    {
        if (!$documentId) {
            $documentId = evo()->documentObject['id'] ?? 0;
        }

        if (!$lang) {
            $lang = evo()->getConfig('lang', 'base');
        }

        $query = sGalleryModel::lang($lang)->whereParent($documentId)->whereItemType($resourceType);

        if ($block && trim($block)) {
            $query->whereBlock($block);
        }

        return $query->orderBy('position')->firstOrNew();
    }

    /**
     * Determine if the given type is an image.
     *
     * @param string $type The type to check.
     * @return bool Returns true if the type is an image, false otherwise.
     */
    public function hasImage(string $type): bool
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_IMAGE);
    }

    /**
     * Check if the given type has video.
     *
     * @param string $type The type to be checked.
     * @return bool Returns true if the type has video, otherwise false.
     */
    public function hasVideo(string $type): bool
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_VIDEO);
    }

    /**
     * Determine if the given type is a YouTube type.
     *
     * @param string $type The type to check.
     * @return bool True if the type is a YouTube type, otherwise false.
     */
    public function hasYoutube(string $type): bool
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_YOUTUBE);
    }

    /**
     * Check if the given type is a PDF.
     *
     * @param string $type The type to be checked.
     * @return bool Returns true if the given type is PDF, otherwise false.
     */
    public function hasPdf(string $type): bool
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_PDF);
    }

    /**
     * Generate language tabs based on the 's_lang_config' configuration value.
     *
     * @return array The language tabs array.
     */
    public function langTabs(): array
    {
        $tabs = [];
        $lang = explode(',', evo()->getConfig('s_lang_config', 'base'));
        foreach ($lang as $item) {
            $tabs[$item] = '<span class="badge bg-seigerit-gallery">' . $item . '</span>';
        }
        return $tabs;
    }

    /**
     * Set the view type.
     *
     * @param string $viewType View type (e.g., 'tab', 'section').
     * @return self
     */
    public function viewType(string $viewType): self
    {
        $this->builder->viewType($viewType);
        return $this;
    }

    /**
     * Set the item type.
     *
     * @param string $itemType Item type (e.g., 'resource').
     * @return self
     */
    public function itemType(string $itemType): self
    {
        $this->builder->itemType($itemType);
        return $this;
    }

    /**
     * Set the ID type.
     *
     * @param string $idType ID type (e.g., 'id', 'document').
     * @return self
     */
    public function idType(string $idType): self
    {
        $this->builder->idType($idType);
        return $this;
    }

    /**
     * Set the block name.
     *
     * @param string $blockName Name of the block.
     * @return self
     */
    public function blockName(string $blockName): self
    {
        $this->builder->blockName($blockName);
        return $this;
    }

    /**
     * Get the default fit method for the image.
     *
     * @return string The default fit method.
     */
    public function defaultFit(): string
    {
        return self::DEFAULT_FIT;
    }

    /**
     * Get the default image width.
     *
     * @return int The default width value.
     */
    public function defaultWidth(): int
    {
        return self::DEFAULT_WIDTH;
    }

    /**
     * Get the default image height.
     *
     * @return int The default height value.
     */
    public function defaultHeight(): int
    {
        return self::DEFAULT_HEIGHT;
    }
}
