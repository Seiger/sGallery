<?php namespace Seiger\sGallery;

use Illuminate\Support\Str;
use Illuminate\View\View;
use phpthumb;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Controllers\sGalleryController;
use Seiger\sGallery\Models\sGalleryModel;
use WebPConvert\WebPConvert;

/**
 * Class sGallery
 *
 * This class provides methods to work with galleries, including checking if a type is an image or video,
 * initializing a gallery, retrieving galleries, resizing images, and more.
 */
class sGallery
{
    public const DEFAULT_WIDTH = 240;
    public const DEFAULT_HEIGHT = 120;
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
     * @return string The URL of the manipulated file.
     */
    public function file(string $input): sGalleryBuilder
    {
        return $this->builder->file($input);
    }

    /**
     * Initialize the Gallery with the specified parameters.
     *
     * @param string $viewType The type of view (default is 'tab')
     * @param string $resourceType The type of resource (default is 'resource')
     * @param string $idType The type of id (default is 'id')
     * @param string $blockName The type of block name (default is '1')
     * @return View|string The initialized view or error string
     * @deprecated Use the new Builder pattern with initialiseView() instead.
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
            return $sGalleryController->index(); // Assuming this returns a View object
        } catch (\Exception $e) {
            // Handle any exceptions and return an error message as a string
            return "Error initializing gallery: " . $e->getMessage();
        }
    }

    /**
     * Retrieves all galleries of a given type and language for a given document using the new Builder pattern.
     *
     * @param string|null $resourceType The type of resources to retrieve.
     * @param int|null $documentId The ID of the document to retrieve resources for.
     * @param string|null $lang The language of the resources to retrieve.
     * @return object The galleries matching the given resource type, document ID, and language.
     */
    public function allGalleries(string $resourceType = 'resource', int $documentId = null, string $lang = null): object
    {
        return $this->resourceType($resourceType)
            ->idType($documentId)
            ->initialiseView();
    }

    /**
     * Retrieve all galleries with block name for a given resource type, document ID, and language using the new Builder pattern.
     *
     * @param string $blockName The name of the block.
     * @param string|null $resourceType The type of resource.
     * @param int|null $documentId The ID of the document to block gallery.
     * @param string|null $lang The language to use.
     * @return object The galleries matching the given resource type, block name, document ID, and language.
     */
    public function blockGalleries(string $blockName = '1', string $resourceType = 'resource', int $documentId = null, string $lang = null): object
    {
        return $this->blockName($blockName)
            ->resourceType($resourceType)
            ->idType($documentId)
            ->initialiseView();
    }

    /**
     * Gets the first gallery object using the new Builder pattern.
     *
     * @param string $resourceType The type of resource.
     * @param int|null $documentId The ID of the document.
     * @param string|null $lang The language of the resource.
     * @param string|null $block The block name if you need block filter.
     * @return object The first object from the sGalleryModel.
     */
    public function firstGallery(string $resourceType = 'resource', int $documentId = null, string $lang = null, string $block = null): object
    {
        return $this->resourceType($resourceType)
            ->idType($documentId)
            ->blockName($block)
            ->initialiseView();
    }

    /**
     * Crop an image using the new Builder pattern.
     *
     * @param string $input The path of the input image file.
     * @param int $width An integer of parameter for width the image.
     * @param int $height An integer of parameter for height the image.
     * @param string $position A string of parameters for crop from center/top/bottom/left/right.
     * @return string The URL of the cropped image.
     */
    public function cropImage(string $input, int $width, int $height, string $position = 'C'): string
    {
        return $this->resizeImage($input, ['w' => $width, 'h' => $height, 'zc' => $position]);
    }

    // Deprecated methods

    /**
     * @deprecated Use allGalleries() method instead with the new Builder pattern.
     */
    public function all(string $resourceType = 'resource', int $documentId = null, string $lang = null): object
    {
        trigger_error('Method all() is deprecated. Use sGallery::allGalleries() with the new Builder pattern instead.', E_USER_DEPRECATED);

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

    /**
     * @deprecated Use blockGalleries() method instead with the new Builder pattern.
     */
    public function block(string $blockName = '1', string $resourceType = 'resource', int $documentId = null, string $lang = null): object
    {
        trigger_error('Method block() is deprecated. Use sGallery::blockGalleries() with the new Builder pattern instead.', E_USER_DEPRECATED);

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

    /**
     * @deprecated Use firstGallery() method instead with the new Builder pattern.
     */
    public function first(string $resourceType = 'resource', int $documentId = null, string $lang = null, string $block = null): object
    {
        trigger_error('Method first() is deprecated. Use sGallery::firstGallery() with the new Builder pattern instead.', E_USER_DEPRECATED);

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
     * @deprecated Use resizeImage() method instead with the new Builder pattern.
     */
    public function resize(string $input, array $params = []): string
    {
        trigger_error('Method resize() is deprecated. Use sGallery::file()->resize() with the new Builder pattern instead.', E_USER_DEPRECATED);

        // Original resize implementation remains here for backward compatibility
        $input = str_replace([MODX_SITE_URL, MODX_BASE_PATH], '', $input);
        $input = str_replace(['//', '///'], '', $input);
        $input = trim($input, '/');
        $params['f'] = $params['f'] ?? 'webp';

        if ($params['f'] == 'webp') {
            $webp = true;
            $dotArr = explode('.', $input);
            $params['f'] = strtolower(end($dotArr));
        }

        $params['zc'] = $params['zc'] ?? 'C';
        $quality = 100;

        if ($params['f'] == 'png') {
            if (isset($params['q']) && $params['q'] > 9) {
                $params['q'] = round((100 - $params['q']) / 10);
            } else {
                $quality = -1;
            }
        }
        $params['thumbnailQuality'] = $params['q'] ?? $quality;
        $params['q'] = $params['q'] ?? $quality;

        if (!empty($input) && strtolower(substr($input, -4)) == '.svg') {
            return $input;
        }

        $newFolderAccessMode = evo()->getConfig('new_folder_permissions');
        $newFolderAccessMode = empty($new) ? 0777 : octdec($newFolderAccessMode);

        $defaultCacheFolder = 'assets/cache/';
        $cacheFolder = $defaultCacheFolder . 'sgallery';

        $path = MODX_BASE_PATH . $cacheFolder;
        if (!file_exists($path) && mkdir($path) && is_dir($path)) {
            chmod($path, $newFolderAccessMode);
        }

        if (!empty($input)) {
            $input = rawurldecode($input);
        }

        if (empty($input) || !file_exists(MODX_BASE_PATH . $input)) {
            $input = isset($noImage) ? $noImage : str_replace(MODX_BASE_PATH, '', __DIR__) . '/../images/noimage.png';
        }

        if (!file_exists(MODX_BASE_PATH . $cacheFolder . '/.htaccess') &&
            $cacheFolder !== $defaultCacheFolder &&
            strpos($cacheFolder, $defaultCacheFolder) === 0
        ) {
            file_put_contents(MODX_BASE_PATH . $cacheFolder . '/.htaccess', "order deny,allow\nallow from all\n");
        }

        $path_parts = pathinfo($input);
        $pattern = '/assets\/sgallery\/(\w+)\/(\d+)/';
        $tmpImagesFolder = preg_replace($pattern, '', $path_parts['dirname']);
        $tmpImagesFolder = explode('/', $tmpImagesFolder);

        foreach ($tmpImagesFolder as $folder) {
            if (!empty($folder)) {
                $cacheFolder .= '/' . $folder;
                $path = MODX_BASE_PATH . $cacheFolder;
                if (!file_exists($path) && mkdir($path) && is_dir($path)) {
                    chmod($path, $newFolderAccessMode);
                }
            }
        }

        $fNamePref = rtrim($cacheFolder, '/') . '/';
        $fName = $path_parts['filename'];
        $fNameSuf = '-' .
            (isset($params['w']) ? $params['w'] : '') . 'x' . (isset($params['h']) ? $params['h'] : '') . '-' .
            substr(md5(serialize($params) . filemtime(MODX_BASE_PATH . $input)), 0, 3) .
            '.' . $params['f'];

        $outputFilename = MODX_BASE_PATH . $fNamePref . $fName . $fNameSuf;

        if (isset($webp)) {
            $check = str_replace($params['f'], 'webp', $outputFilename);
        } else {
            $check = $outputFilename;
        }

        if (!file_exists($check)) {
            $phpThumb = new phpthumb();
            $phpThumb->config_cache_directory = MODX_BASE_PATH . $defaultCacheFolder;
            $phpThumb->config_temp_directory = "/tmp";
            $phpThumb->config_document_root = MODX_BASE_PATH;
            $phpThumb->setSourceFilename(MODX_BASE_PATH . $input);
            foreach ($params as $key => $value) {
                $phpThumb->setParameter($key, $value);
            }

            if ($phpThumb->GenerateThumbnail()) {
                $phpThumb->RenderToFile($outputFilename);
            } else {
                evo()->logEvent(0, 3, implode('<br/>', $phpThumb->debugmessages), 'phpthumb');
            }

            if (isset($webp) && class_exists('\WebPConvert\WebPConvert')) {
                if (is_cli() || (isset($_SERVER['HTTP_ACCEPT']) && (strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') || $_SERVER['HTTP_ACCEPT'] == "*/*") !== false) && pathinfo($outputFilename, PATHINFO_EXTENSION) != 'gif') {
                    if (!file_exists($check)) {
                        WebPConvert::convert($outputFilename, $check, ['quality' => 100]);
                    }
                    $fNameSuf = str_replace($params['f'], 'webp', $fNameSuf);
                }
            }
        }

        if (isset($webp) && file_exists($check)) {
            $fNameSuf = str_replace($params['f'], 'webp', $fNameSuf);
        }

        return MODX_SITE_URL . $fNamePref . rawurlencode($fName) . $fNameSuf;
    }

    /**
     * @deprecated Use cropImage() method instead with the new Builder pattern.
     */
    public function crop(string $input, int $width, int $height, string $position = 'C'): string
    {
        trigger_error('Method crop() is deprecated. Use sGallery::cropImage() with the new Builder pattern instead.', E_USER_DEPRECATED);
        return $this->resize($input, ['w' => $width, 'h' => $height, 'zc' => $position]);
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
