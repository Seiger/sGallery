<?php namespace Seiger\sGallery\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Seiger\sGallery\Models\sGalleryField;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\Services\GalleryAccess;
use Seiger\sGallery\Services\GalleryFiles;
use Seiger\sGallery\Services\GalleryMedia;

/** Handle manager gallery actions using the authorized parent and persisted media location. */
class sGalleryController
{
    protected string $viewType;
    protected string $itemType;
    protected string $idType;
    protected string $blockName;

    /**
     * Set the view binding supplied by PHP. HTTP gallery routes resolve scope from
     * their request separately, so an embedded gallery keeps its configured scope.
     */
    public function __construct(string $viewType = sGalleryModel::VIEW_SECTION, string $itemType = 'resource', string $idType = 'id', string $blockName = '1')
    {
        $this->viewType = in_array($viewType, [
            sGalleryModel::VIEW_TAB, sGalleryModel::VIEW_SECTION, sGalleryModel::VIEW_SECTION_DOWNLOADS,
        ], true) ? $viewType : sGalleryModel::VIEW_TAB;
        GalleryAccess::require(GalleryFiles::identifier($itemType) && GalleryFiles::identifier($blockName) &&
            preg_match('/\A[A-Za-z_][A-Za-z0-9_-]{0,63}\z/', $idType), 422);
        $this->itemType = $itemType;
        $this->idType = $idType;
        $this->blockName = $blockName;
    }

    /** Render a manager gallery after checking access to the selected parent and block. */
    public function index(?Request $request = null): View
    {
        if ($request !== null) {
            $this->requestScope($request);
        }
        $input = ($request ?? request())->input($this->idType);
        $parent = GalleryFiles::id($input);
        if ($parent === null) {
            // The embedded gallery may be rendered while a parent is still unsaved.
            GalleryAccess::require($request === null && in_array($input, [null, '', 0, '0'], true), 422);
            $galleries = collect();
        } else {
            GalleryAccess::authorize('read', $this->itemType, $parent, $this->blockName);
            $galleries = sGalleryModel::whereParent($parent)->whereBlock($this->blockName)
                ->whereItemType($this->itemType)->orderBy('position')->get();
        }
        return $this->view($this->viewType, ['galleries' => $galleries, 'sGalleryController' => $this]);
    }

    /** Render a gallery selected by the manager route's request. @since 1.8.0 */
    public function show(Request $request): View
    {
        return $this->index($request);
    }

    /** Store an approved image, audio or video file in the selected gallery. */
    public function uploadFile(Request $request): JsonResponse
    {
        return $this->storeUpload($request, 'media');
    }

    /** Store an approved downloadable document using the CMS document formats. */
    public function uploadDownload(Request $request): JsonResponse
    {
        return $this->storeUpload($request, 'download');
    }

    /** Read the manager route's logical type and block, including older encoded parameter names. @since 1.8.0 */
    private function requestScope(Request $request): void
    {
        $type = $request->input('amp;itemType', $request->input('itemType', 'resource'));
        $block = $request->input('amp;block', $request->input('block', '1'));
        GalleryAccess::require(GalleryFiles::identifier($type) && GalleryFiles::identifier($block), 422);
        $this->itemType = $type;
        $this->blockName = $block;
    }

    /** Resolve and authorize the parent for actions whose scope arrives in the request. @since 1.8.0 */
    private function authorizeParent(Request $request, string $action): int
    {
        $this->requestScope($request);
        $parent = GalleryFiles::id($request->input('cat'));
        GalleryAccess::require($parent !== null, 422);
        GalleryAccess::authorize($action, $this->itemType, $parent, $this->blockName);
        return $parent;
    }

    /** Load an item by ID and authorize its persisted parent, type and block. @since 1.8.0 */
    private function authorizeItem(mixed $input, string $action): sGalleryModel
    {
        $id = GalleryFiles::id($input);
        GalleryAccess::require($id !== null, 422);
        $gallery = sGalleryModel::findOrFail($id);
        $parent = GalleryFiles::id($gallery->parent);
        GalleryAccess::require($parent !== null && GalleryFiles::identifier($gallery->item_type) && GalleryFiles::identifier($gallery->block), 403);
        GalleryAccess::authorize($action, $gallery->item_type, $parent, $gallery->block);
        return $gallery;
    }

    /**
     * Publish under a fresh name after validation. If record creation fails, remove
     * the new file so a retry starts from a clean state.
     *
     * @since 1.8.0
     */
    private function storeUpload(Request $request, string $group): JsonResponse
    {
        $parent = $this->authorizeParent($request, $group === 'media' ? 'upload' : 'uploadDownload');
        $file = $request->file('file');
        if (!$file instanceof \Illuminate\Http\UploadedFile || !$file->isValid() || !$file->getSize() ||
            $file->getSize() > (int) evo()->getConfig('upload_maxsize', 2097152)) {
            return response()->json(['success' => 0, 'error' => __('validation.uploaded', ['attribute' => 'file'])], 422);
        }
        try {
            $media = GalleryMedia::classify($file->getPathname(), $file->getClientOriginalExtension(), $group);
        } catch (RuntimeException $error) {
            return response()->json(['success' => 0, 'error' => $error->getMessage()], 422);
        }

        $relative = GalleryFiles::uploadDirectory($this->itemType, $parent);
        $directory = GalleryFiles::directory($relative, true);
        if ($directory === null) {
            return response()->json(['success' => 0, 'error' => __('sGallery::manager.file_upload_error')], 422);
        }

        $slug = substr(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 0, 120);
        $filename = ($slug ?: 'file') . '-' . bin2hex(random_bytes(16)) . '.' . $media['extension'];
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        $reserved = @fopen($path, 'x');
        if ($reserved === false) {
            return response()->json(['success' => 0, 'error' => __('sGallery::manager.file_upload_error')], 500);
        }
        fclose($reserved);
        try {
            $file->move($directory, $filename);
            if (!chmod($path, 0644 & ~umask())) {
                throw new RuntimeException('Could not set gallery file permissions.');
            }
            return DB::transaction(fn () => $this->previewResponse(
                $this->saveGalleryFile($parent, $filename, $media['type']), 'Uploaded Successfully!'
            ));
        } catch (\Throwable $error) {
            if (is_file($path) && !@unlink($path)) {
                Log::error('Could not clean up a failed gallery upload.');
            }
            Log::error('Could not save a gallery upload.', ['exception' => $error]);
            return response()->json(['success' => 0, 'error' => __('sGallery::manager.file_upload_error')], 500);
        }
    }

    /** Attach a parsed YouTube video ID to an authorized gallery without a network lookup. */
    public function addYoutube(Request $request): JsonResponse
    {
        $parent = $this->authorizeParent($request, 'addYoutube');
        $link = $request->input('youtubeLink');
        $url = is_string($link) ? parse_url($link) : false;
        $host = strtolower($url['host'] ?? '');
        $path = $url['path'] ?? '';
        $id = null;
        if (in_array($url['scheme'] ?? '', ['https', 'http'], true)) {
            if ($host === 'youtu.be') {
                $id = ltrim($path, '/');
            } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
                if ($path === '/watch') {
                    parse_str($url['query'] ?? '', $query);
                    $id = $query['v'] ?? null;
                } elseif (preg_match('~\A/(?:embed|shorts|live)/([^/]+)\z~', $path, $match)) {
                    $id = $match[1];
                }
            }
        }
        if (!is_string($id) || !preg_match('/\A[A-Za-z0-9_-]{11}\z/', $id)) {
            return response()->json(['success' => 0, 'error' => 'Invalid YouTube link.'], 422);
        }

        return $this->previewResponse($this->saveGalleryFile($parent, $id, 'youtube'), 'Add Successfully!');
    }

    /** Register an existing browser file as a shared reference, without taking ownership of it. */
    public function uploadEvoLibrary(Request $request): JsonResponse
    {
        $parent = $this->authorizeParent($request, 'register');
        GalleryAccess::authorizeLibrary();
        $input = $request->input('file');
        GalleryAccess::require(is_string($input), 422);
        if (str_starts_with($input, EVO_SITE_URL)) {
            $input = substr($input, strlen(EVO_SITE_URL));
        }
        $input = rawurldecode(ltrim($input, '/'));
        $name = GalleryFiles::library($input, true);
        $path = $name === null ? null : GalleryFiles::local($name);
        if ($path === null || strlen($name) > 256) {
            return response()->json(['success' => 0, 'error' => 'File not added.'], 422);
        }
        try {
            $media = GalleryMedia::classify($path, pathinfo($name, PATHINFO_EXTENSION), 'library');
        } catch (RuntimeException $error) {
            return response()->json(['success' => 0, 'error' => $error->getMessage()], 422);
        }
        GalleryAccess::authorizeLibrary($media['type']);

        return $this->previewResponse($this->saveGalleryFile($parent, $name, $media['type']), 'Add Successfully!');
    }

    /** Update positions only for the supplied items in one authorized gallery. */
    public function resortGallery(Request $request): void
    {
        $parent = $this->authorizeParent($request, 'reorder');
        $items = $request->input('item');
        GalleryAccess::require(is_array($items) && array_is_list($items) && $items !== [] && count($items) <= 2000, 422);
        $ids = array_map(GalleryFiles::id(...), $items);
        GalleryAccess::require(!in_array(null, $ids, true) && count(array_unique($ids)) === count($ids), 422);

        DB::transaction(function () use ($parent, $ids) {
            $galleries = sGalleryModel::whereParent($parent)->whereBlock($this->blockName)
                ->whereItemType($this->itemType)->orderBy('position')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $existing = $galleries->keys()->all();
            GalleryAccess::require(!array_diff($ids, $existing), 422);
            $ordered = array_merge($ids, array_diff($existing, $ids));
            foreach ($ordered as $position => $id) {
                $galleries[$id]->update(['position' => $position]);
            }
        });
    }

    /** Read the translation form for an authorized gallery item. */
    public function getTranslate(Request $request): JsonResponse
    {
        $gallery = $this->authorizeItem($request->input('item'), 'read');
        $items = sGalleryField::where('key', $gallery->id)->get()->keyBy('lang');
        return response()->json([
            'success' => 1,
            'message' => 'Get Successfully!',
            'tabs' => $this->view('partials.tabs', ['items' => $items, 'key' => $gallery->id])->render(),
        ]);
    }

    /** Save one authorized item's configured language fields as one transaction. */
    public function setTranslate(Request $request): JsonResponse
    {
        $list = $request->input('list');
        GalleryAccess::require(is_array($list) && count($list) === 1, 422);
        $gallery = $this->authorizeItem(array_key_first($list), 'translate');
        $languages = reset($list);
        GalleryAccess::require(is_array($languages) && $languages !== [], 422);
        $allowed = array_map('trim', explode(',', (string) evo()->getConfig('s_lang_config', 'base')));
        $fields = ['alt' => 100, 'title' => 100, 'description' => 1024, 'link_text' => 100, 'link' => 1024];
        $translations = [];
        foreach ($languages as $lang => $item) {
            GalleryAccess::require(is_string($lang) && preg_match('/\A[A-Za-z0-9_-]{1,4}\z/', $lang) &&
                in_array($lang, $allowed, true) && is_array($item) && !array_diff_key($item, $fields), 422);
            foreach ($fields as $field => $limit) {
                $value = $item[$field] ?? '';
                GalleryAccess::require(is_string($value) && mb_strlen($value) <= $limit, 422);
                $translations[$lang][$field] = $value;
            }
            $link = trim($translations[$lang]['link']);
            GalleryAccess::require($link === '' || GalleryMedia::link($link), 422);
            $translations[$lang]['link'] = $link;
        }

        DB::transaction(function () use ($gallery, $translations) {
            sGalleryModel::query()->lockForUpdate()->findOrFail($gallery->id);
            foreach ($translations as $lang => $fields) {
                sGalleryField::updateOrCreate(['key' => $gallery->id, 'lang' => $lang], $fields);
            }
        });

        return response()->json(['success' => 1, 'message' => __('sGallery::manager.saved_successfully')]);
    }

    /** Persist the gallery entry and ensure its default translation exists without replacing edits. */
    protected function saveGalleryFile(int $parent, string $filename, string $filetype): sGalleryModel
    {
        return DB::transaction(function () use ($parent, $filename, $filetype) {
            $gallery = sGalleryModel::updateOrCreate(
                ['parent' => $parent, 'block' => $this->blockName, 'item_type' => $this->itemType, 'file' => $filename],
                ['type' => $filetype]
            );
            sGalleryField::firstOrCreate(['key' => $gallery->id, 'lang' => evo()->getConfig('lang', 'base')]);
            return $gallery;
        });
    }

    /** Render one of the registered media previews after saving the entry. @since 1.8.0 */
    private function previewResponse(sGalleryModel $gallery, string $message): JsonResponse
    {
        return response()->json(['success' => 1, 'message' => $message,
            'preview' => $this->view(GalleryMedia::preview($gallery->type), [
                'gallery' => $gallery, 'sGalleryController' => $this,
            ])->render()]);
    }

    /**
     * Detach shared media and delete owned media only after the final reference.
     * Move the owned file aside first so a failed DB deletion can restore it.
     */
    public function delete(Request $request): JsonResponse
    {
        $gallery = $this->authorizeItem($request->input('item'), 'delete');
        $pending = $path = null;
        try {
            DB::transaction(function () use ($gallery, &$pending, &$path) {
                $gallery = sGalleryModel::query()->lockForUpdate()->findOrFail($gallery->id);
                $relative = GalleryFiles::owned($gallery);
                if ($relative !== null && !sGalleryModel::whereKeyNot($gallery->id)->where(function ($query) use ($gallery, $relative) {
                    $query->where('file', $relative)->orWhere(function ($query) use ($gallery) {
                        $query->where('file', $gallery->file)->where('item_type', $gallery->item_type)->where('parent', $gallery->parent);
                    });
                })->exists()) {
                    $path = GalleryFiles::local($relative);
                    if ($path === null) {
                        throw new RuntimeException('Gallery file changed during deletion.');
                    }
                    $target = dirname($path) . DIRECTORY_SEPARATOR . '.sgallery-' . bin2hex(random_bytes(16)) . '.pending';
                    if (!@rename($path, $target)) {
                        throw new RuntimeException('Could not remove gallery file.');
                    }
                    $pending = $target;
                }
                sGalleryField::where('key', $gallery->id)->delete();
                if (!$gallery->delete()) {
                    throw new RuntimeException('Could not remove gallery entry.');
                }
            });
        } catch (\Throwable $error) {
            if ($pending !== null && !@rename($pending, $path)) {
                Log::error('Could not restore gallery file after database failure.');
            }
            throw $error;
        }
        if ($pending !== null && !@unlink($pending)) {
            Log::error('Could not clean up a detached gallery file.');
        }

        return response()->json(['success' => 1, 'message' => __('sGallery::manager.deleted')]);
    }

    /**
     * Get the identifier type
     *
     * @return string
     */
    public function getIdType()
    {
        return $this->idType;
    }

    /**
     * Get the resource type
     *
     * @return string
     */
    public function getItemType()
    {
        return $this->itemType;
    }

    /**
     * Get the block name
     *
     * @return string
     */
    public function getBlockName()
    {
        return $this->blockName;
    }

    /**
     * Get the block name id
     *
     * @return string
     */
    public function getBlockNameId($blockName = '')
    {
        if (!trim($blockName)) {
            $blockName = $this->blockName;
        }
        return Str::of($blockName)->slug()->camel()->ucfirst();
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'title.required' => 'A title is required',
            'body.required' => 'A message is required',
        ];
    }

    /**
     * Display render
     *
     * @param string $tpl
     * @param array $data
     * @return bool
     */
    public function view(string $tpl, array $data = [])
    {
        return \View::make(\Seiger\sGallery\Services\GallerySettings::view($tpl), $data);
    }

}
