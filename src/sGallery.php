<?php namespace Seiger\sGallery;

use phpthumb;
use Seiger\sGallery\Models\sGalleryModel;
use WebPConvert\WebPConvert;

class sGallery
{
    /**
     * Get all files from current document
     *
     * @param int|null $documentId
     * @param string|null $lang
     * @return object
     */
    public function all(int $documentId = null, string $lang = null): object
    {
        if (!$documentId) {
            $documentId = evo()->documentObject['id'] ?? 0;
        }

        if (!$lang) {
            $lang = evo()->getConfig('lang', 'base');
        }

        $galleries = sGalleryModel::lang($lang)
            ->whereParent($documentId)
            ->orderBy('position')
            ->get();

        return $galleries;
    }

    /**
     * Resize Image
     * https://docs.evo.im/ua/04_extras/phpthumb/02_opcii.html
     *
     * @param string $input
     * @param array $params
     * @return array|string|string[]
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
            $input = isset($noImage) ? $noImage : __DIR__ . '../images/noimage.png';
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
            //dd($phpThumb->thumbnailQuality);
            if ($phpThumb->GenerateThumbnail()) {
                $phpThumb->RenderToFile($outputFilename);
            } else {
                evo()->logEvent(0, 3, implode('<br/>', $phpThumb->debugmessages), 'phpthumb');
            }

            if (isset($webp) && class_exists('\WebPConvert\WebPConvert')) {
                if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false && pathinfo($outputFilename, PATHINFO_EXTENSION) != 'gif') {
                    $outputFilename = str_replace('.'.$params['f'], '', $outputFilename);
                    $fNameSuf = str_replace('.'.$params['f'], '', $fNameSuf);
                    if (file_exists($outputFilename . '.webp')) {
                        $fNameSuf .= '.webp';
                    } else {
                        WebPConvert::convert($outputFilename.'.'.$params['f'], $outputFilename . '.webp', ['quality' => 100]);
                        $fNameSuf .= '.webp';
                    }
                    unlink($outputFilename.'.'.$params['f']);
                }
            }
        }

        if (isset($webp)) {
            $fNameSuf = str_replace($params['f'], 'webp', $fNameSuf);
        }

        return MODX_SITE_URL . $fNamePref . rawurlencode($fName) . $fNameSuf;
    }

    /**
     * Content Tabs
     *
     * @return array
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
}