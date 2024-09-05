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
     * Initialize the view builder.
     *
     * @return sGalleryBuilder
     */
    public function initialiseView(): sGalleryBuilder
    {
        return $this->builder->initialise();
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
     * @deprecated Use initialiseView() instead.
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
     * Get the first gallery object using the Builder pattern.
     *
     * @param string $resourceType The type of resource.
     * @param int|null $documentId The ID of the document.
     * @param string|null $lang The language of the resource.
     * @param string|null $block The block name for filtering.
     * @return object The first matching gallery object.
     */
    public function firstGallery(string $resourceType = 'resource', int $documentId = null, string $lang = null, string $block = null): object
    {
        return $this->resourceType($resourceType)
            ->idType($documentId)
            ->blockName($block)
            ->initialiseView();
    }

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
            ->whereResourceType($resourceType)
            ->orderBy('position')
            ->get();
    }

    public function block(string $blockName = '1', string $resourceType = 'resource', int $documentId = null, string $lang = null): object
    {
        if (!$documentId) {
            $documentId = evo()->documentObject['id'] ?? 0;
        }

        if (!$lang) {
            $lang = evo()->getConfig('lang', 'base');
        }

        return sGalleryModel::lang($lang)
            ->whereParent($documentId)
            ->whereBlock($blockName)
            ->whereResourceType($resourceType)
            ->orderBy('position')
            ->get();
    }

    public function first(string $resourceType = 'resource', int $documentId = null, string $lang = null, string $block = null): object
    {
        if (!$documentId) {
            $documentId = evo()->documentObject['id'] ?? 0;
        }

        if (!$lang) {
            $lang = evo()->getConfig('lang', 'base');
        }

        $query = sGalleryModel::lang($lang)->whereParent($documentId)->whereResourceType($resourceType);

        if ($block && trim($block)) {
            $query->whereBlock($block);
        }

        return $query->orderBy('position')->firstOrNew();
    }

    /**
     * Determines if the given type is an image.
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
     * Determines if the given type is a YouTube type.
     *
     * @param string $type The type to check.
     * @return bool True if the type is a YouTube type, otherwise false.
     */
    public function hasYoutube(string $type): bool
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_YOUTUBE);
    }

    /**
     * Checks if the given type is PDF.
     *
     * @param string $type The type to be checked.
     * @return bool Returns true if the given type is PDF, false otherwise.
     */
    public function hasPdf(string $type): bool
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_PDF);
    }

    /**
     * Generate language tabs.
     *
     * This method generates an array of language tabs based on the 's_lang_config' configuration value.
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
     * @param string $viewType
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
     * @param string $itemType
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
     * @param string $idType
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
     * @param string $blockName
     * @return self
     */
    public function blockName(string $blockName): self
    {
        $this->builder->blockName($blockName);
        return $this;
    }

    /**
     * Gets the default image width.
     *
     * This method returns the default width value defined as a constant in the class.
     *
     * @return int The default width value.
     */
    public function defaultFit(): string
    {
        return self::DEFAULT_FIT;
    }

    /**
     * Gets the default image width.
     *
     * This method returns the default width value defined as a constant in the class.
     *
     * @return int The default width value.
     */
    public function defaultWidth(): int
    {
        return self::DEFAULT_WIDTH;
    }

    /**
     * Gets the default image height.
     *
     * This method returns the default height value defined as a constant in the class.
     *
     * @return int The default height value.
     */
    public function defaultHeight(): int
    {
        return self::DEFAULT_HEIGHT;
    }
}
