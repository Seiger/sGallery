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
     * @param string|null $input The path of the input file.
     * @return sGalleryBuilder The builder instance to chain further operations.
     */
    public function file(string|null $input): sGalleryBuilder
    {
        $input = trim($input ?? sGalleryModel::NOIMAGE);

        if (empty($input)) {
            $input = sGalleryModel::NOIMAGE;
        }

        return $this->builder->file($input);
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
     * Check if the given string contains a valid HTTP or HTTPS link.
     *
     * This method determines if the provided string starts with "https://" or "http://".
     *
     * @param string|null $string The string to be checked.
     * @return bool Returns true if the string starts with "https://" or "http://", otherwise false.
     */
    public static function hasLink(string|null $string): bool
    {
        if (!$string) {
            return false;
        }

        return str_starts_with($string, 'https://') || str_starts_with($string, 'http://');
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

    /**
     * Generate a URL from the route name with an action ID appended.
     *
     * @param string $name Route name
     * @return string
     */
    public function route(string $name, array $parameters = []): string
    {
        // Generate the base route URL and remove trailing slashes
        $route = route($name, $parameters);

        // Trim friendly URL suffix
        if (!empty(evo()->getConfig('friendly_url_suffix'))) {
            $route = rtrim($route, evo()->getConfig('friendly_url_suffix'));
        }

        return $route;
    }

    /**
     * Determine the URL scheme (HTTP or HTTPS) based on server variables.
     *
     * @param string $url URL to modify
     * @return string
     */
    public function scheme(string $url): string
    {
        // Determine the current scheme from various sources
        $scheme = 'http'; // Default to HTTP

        // Check the server variables for HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        // Check the forward headers if present
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $scheme = 'https';
        }
        // Check the HTTP_HOST for known HTTPS setups
        elseif (isset($_SERVER['HTTP_HOST']) && preg_match('/^https:/i', $_SERVER['HTTP_HOST'])) {
            $scheme = 'https';
        }
        // Check if the server is using HTTPS by default
        elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            $scheme = 'https';
        }

        // Replace the scheme in the URL if necessary
        return preg_replace('/^http:\/\//', $scheme . '://', $url);
    }
}
