<?php namespace Seiger\sGallery;

use Illuminate\Support\Str;
use Illuminate\View\View;
use phpthumb;
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
    const DEFAULT_WIDTH = 240;
    const DEFAULT_HEIGHT = 120;

    /**
     * Determines if the given type is an image.
     *
     * @param string $type The type to check
     * @return bool Returns true if the type is an image, false otherwise
     */
    public function hasImage($type)
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_IMAGE);
    }

    /**
     * Check if the given type has video
     *
     * @param string $type The type to be checked
     * @return bool Returns true if the type has video, otherwise false
     */
    public function hasVideo($type)
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_VIDEO);
    }

    /**
     * Determines if the given type is a YouTube type
     *
     * @param string $type The type to check
     * @return bool True if the type is a YouTube type, otherwise false
     */
    public function hasYoutube($type)
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_YOUTUBE);
    }

    /**
     * Checks if the given type is PDF.
     *
     * @param string $type The type to be checked.
     *
     * @return bool Returns true if the given type is PDF, false otherwise.
     */
    public function hasPdf($type)
    {
        return Str::of($type)->exactly(sGalleryModel::TYPE_PDF);
    }

    /**
     * Initialize the Gallery with the specified parameters
     *
     * @param string $viewType The type of view (default is 'tab')
     * @param string $resourceType The type of resource (default is 'resource')
     * @param string $idType The type of id (default is 'id')
     * @return View The initialized view
     */
    public function initialise(string $viewType = 'tab', string $resourceType = 'resource', string $idType = 'id'): View
    {
        $sGalleryController = new sGalleryController($viewType, $resourceType, $idType);
        return $sGalleryController->index();
    }

    /**
     * Retrieve all galleries for a given resource type, document ID, and language.
     *
     * @param string $resourceType The type of resource (default: 'resource')
     * @param int|null $documentId The document ID (default: null)
     * @param string|null $lang The language (default: null)
     * @return object The galleries matching the given resource type, document ID, and language.
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
            ->whereResourceType($resourceType)
            ->orderBy('position')
            ->get();
    }

    /**
     * Retrieves the first item from the collection.
     *
     * This method returns the first item from the collection of resources based on the given parameters.
     *
     * @param string $resourceType (Optional) The type of resource to retrieve. Default is 'resource'.
     * @param int|null $documentId (Optional) The ID of the document to retrieve. Default is null.
     * @param string|null $lang (Optional) The language of the resource to retrieve. Default is null.
     * @return object The first item from the collection.
     */
    public function first(string $resourceType = 'resource', int $documentId = null, string $lang = null): object
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
            ->firstOrNew();
    }

    /**
     * Resize an image
     * https://docs.evo.im/ua/04_extras/phpthumb/02_opcii.html
     *
     * @param string $input The path of the input image file
     * @param array $params An array of parameters for resizing the image (optional)
     * @return string The URL of the resized image
     */
    public function resize(string $input, array $params = []): string
    {
        // Set filepath
        $input = str_replace([MODX_SITE_URL, MODX_BASE_PATH], '', $input);
        $input = str_replace(['//', '///'], '', $input);
        $input = trim($input, '/');

        // Set output format
        $params['f'] = $params['f'] ?? 'webp';
        if ($params['f'] == 'webp') {
            $webp = true;
            $params['f'] = strtolower(substr($input, -3));
        }

        // Set resize type
        $params['zc'] = $params['zc'] ?? 'C';

        // Set output Quality
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
        $cacheFolder = isset($cacheFolder) ? $cacheFolder : $defaultCacheFolder . 'sgallery';

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
                if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false && pathinfo($outputFilename, PATHINFO_EXTENSION) != 'gif') {
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
     * Generate language tabs
     *
     * This method generates an array of language tabs based on the 's_lang_config' configuration value
     *
     * @return array The language tabs array
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