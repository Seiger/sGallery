<?php namespace Seiger\sGallery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Seiger\sGallery\Models\sGalleryField;
use Seiger\sGallery\Models\sGalleryModel;
use sGallery;
use function Laravel\Prompts\info;

class sGalleryController
{
    protected $viewType;
    protected $itemType;
    protected $idType;
    protected $blockName;

    /**
     * @param string $viewType tab or section or sectionFiles
     * @param string $itemType resource
     * @param string $idType id
     * @param string $blockName block name
     */
    public function __construct(string $viewType = sGalleryModel::VIEW_SECTION, string $itemType = 'resource', string $idType = 'id', string $blockName = '1')
    {
        // Wiev type
        $viewTypeDef = sGalleryModel::VIEW_TAB;
        if (in_array($viewType, [
            sGalleryModel::VIEW_TAB,
            sGalleryModel::VIEW_SECTION,
            sGalleryModel::VIEW_SECTION_DOWNLOADS
        ])) {
            $viewTypeDef = $viewType;
        }
        $this->viewType = $viewTypeDef;

        // Block name
        if (request()->has('amp;block')) {
            $blockName = request()->get('amp;block');
        } else {
            $blockName = request()->block ?? $blockName;
        }
        $this->blockName = trim($blockName);

        // ID type
        $this->idType = $idType;

        // Resource type
        if (request()->has('amp;itemType')) {
            $itemType = request()->get('amp;itemType');
        } else {
            $itemType = request()->itemType ?? $itemType;
        }
        $this->itemType = trim($itemType, '/');
    }

    /**
     * Show tab page with Gallery files
     *
     * @return \Symfony\Component\HttpFoundation\Response The response
     */
    public function index(): View
    {
        $cat = request()->{$this->idType} ?? 0;
        $galleries = sGalleryModel::whereParent($cat)
            ->whereBlock($this->blockName)
            ->whereItemType($this->itemType)
            ->orderBy('position')
            ->get();
        return $this->view($this->viewType, ['galleries' => $galleries, 'sGalleryController' => $this]);
    }

    /**
     * Upload and save Image file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadFile(Request $request)
    {
        $data = [];

        $validator = Validator::make($request->all(), [
            'cat' => [
                'required',
                'integer',
                'min:1'
            ],
            'file' => [
                'required',
                File::types(explode(',', evo()->getConfig('upload_images', 'png,jpg,jpeg,mp4') . ',' . evo()->getConfig('upload_media', 'mp3,mp4')))
                    ->max($this->maxsize()),
            ]
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first('file'); // Error response
            if ($_FILES['file']['error'] == 1) { // The uploaded file exceeds the upload_max_filesize directive
                $data['error'] = __('validation.max.file', ['attribute' => 'file', 'max' => $this->maxsize()]);
            }
        } else {
            if ($request->file('file')) {
                $file = $request->file('file');
                $filename = $file->getClientOriginalName();
                $filetype = explode('/', $file->getMimeType())[0];
                if (in_array($filetype, ['application'])) {
                    $filetype = explode('/', $file->getMimeType())[1];
                }

                $ext  = pathinfo($filename, PATHINFO_EXTENSION);
                $name = pathinfo($filename, PATHINFO_FILENAME);
                $filename = Str::slug($name) . '.' . strtolower($ext);

                // Upload file
                $file->move(sGalleryModel::UPLOAD.$this->itemType.'/'.$request->cat, $filename);

                $newFolderAccessMode = evo()->getConfig('new_folder_permissions', '');
                $newFolderAccessMode = empty($newFolderAccessMode) ? 0777 : octdec($newFolderAccessMode);
                chmod(sGalleryModel::UPLOAD.$this->itemType.'/'.$request->cat, $newFolderAccessMode);

                $newFileAccessMode = evo()->getConfig('new_file_permissions', '');
                $newFileAccessMode = empty($newFileAccessMode) ? 0666 : octdec($newFileAccessMode);
                chmod(sGalleryModel::UPLOAD.$this->itemType.'/'.$request->cat.'/'.$filename, $newFileAccessMode);

                // Save in DB
                $thisFile = sGalleryModel::whereParent($request->cat)
                    ->whereBlock($this->blockName)
                    ->whereItemType($this->itemType)
                    ->whereFile($filename)
                    ->firstOrCreate();
                $thisFile->parent = $request->cat;
                $thisFile->block = $this->blockName;
                $thisFile->file = $filename;
                $thisFile->type = $filetype;
                $thisFile->item_type = $this->itemType;
                $thisFile->update();

                // Create default texts
                $translate = new sGalleryField();
                $translate->key = $thisFile->id;
                $translate->lang = evo()->getConfig('lang', 'base');
                $translate->save();

                // Response
                $data['success'] = 1;
                $data['message'] = 'Uploaded Successfully!';
                $data['preview'] = $this->view('partials.'.$filetype, ['gallery' => $thisFile, 'sGalleryController' => $this])->render();
            } else {
                // Response
                $data['success'] = 2;
                $data['message'] = 'File not uploaded.';
            }
        }

        return response()->json($data);
    }

    /**
     * Upload and save Download file
     *
     * @param Request $request The HTTP request object
     *
     * @return \Illuminate\Http\JsonResponse The JSON response containing success status, error message (if any) and uploaded file details
     */
    public function uploadDownload(Request $request)
    {
        $data = [];

        $validator = Validator::make($request->all(), [
            'cat' => 'required|integer|min:1',
            'file' => 'required|mimes:'.evo()->getConfig('upload_files', 'odp,odt,pdf,ppt,pptx,doc,docx').'|max:'.$this->maxsize()
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first('file'); // Error response
        } else {
            if ($request->file('file')) {
                $file = $request->file('file');
                $filename = $file->getClientOriginalName();
                $filetype = explode('/', $file->getMimeType())[0];
                if (in_array($filetype, ['application'])) {
                    $filetype = explode('/', $file->getMimeType())[1];
                }

                $ext  = pathinfo($filename, PATHINFO_EXTENSION);
                $name = pathinfo($filename, PATHINFO_FILENAME);
                $filename = Str::slug($name) . '.' . strtolower($ext);

                // Upload file
                $file->move(sGalleryModel::UPLOAD.$this->itemType.'/'.$request->cat, $filename);

                $newFolderAccessMode = evo()->getConfig('new_folder_permissions', '');
                $newFolderAccessMode = empty($newFolderAccessMode) ? 0777 : octdec($newFolderAccessMode);
                chmod(sGalleryModel::UPLOAD.$this->itemType.'/'.$request->cat, $newFolderAccessMode);

                $newFileAccessMode = evo()->getConfig('new_file_permissions', '');
                $newFileAccessMode = empty($newFileAccessMode) ? 0666 : octdec($newFileAccessMode);
                chmod(sGalleryModel::UPLOAD.$this->itemType.'/'.$request->cat.'/'.$filename, $newFileAccessMode);

                // Save in DB
                $thisFile = sGalleryModel::whereParent($request->cat)
                    ->whereBlock($this->blockName)
                    ->whereItemType($this->itemType)
                    ->whereFile($filename)
                    ->firstOrCreate();
                $thisFile->parent = $request->cat;
                $thisFile->block = $this->blockName;
                $thisFile->file = $filename;
                $thisFile->type = $filetype;
                $thisFile->item_type = $this->itemType;
                $thisFile->update();

                // Create default texts
                $translate = new sGalleryField();
                $translate->key = $thisFile->id;
                $translate->lang = evo()->getConfig('lang', 'base');
                $translate->save();

                // Response
                $data['success'] = 1;
                $data['message'] = 'Uploaded Successfully!';
                $data['preview'] = $this->view('partials.'.$filetype, ['gallery' => $thisFile, 'sGalleryController' => $this])->render();
            } else {
                // Response
                $data['success'] = 2;
                $data['message'] = 'File not uploaded.';
            }
        }

        return response()->json($data);
    }

    /**
     * Add a YouTube video
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addYoutube(Request $request)
    {
        $data = [];

        $validator = Validator::make($request->all(), [
            'cat' => 'required|integer|min:1',
            'youtubeLink' => 'required|active_url'
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first(); // Error response
        } else {
            $r = '/(?im)\b(?:https?:\/\/)?(?:w{3}\.)?youtu(?:be)?\.(?:com|be)\/(?:(?:\??v=?i?=?\/?)|watch\?vi?=|watch\?.*?&v=|embed\/|)([A-Z0-9_-]{11})\S*(?=\s|$)/';
            preg_match_all($r, $request->input('youtubeLink'), $matches, PREG_SET_ORDER, 0);
            if (isset($matches[0][1])) {
                // Save in DB
                $thisFile = sGalleryModel::whereParent($request->cat)
                    ->whereBlock($this->blockName)
                    ->whereItemType($this->itemType)
                    ->whereFile($matches[0][1])
                    ->firstOrCreate();
                $thisFile->parent = $request->cat;
                $thisFile->block = $this->blockName;
                $thisFile->file = $matches[0][1];
                $thisFile->type = 'youtube';
                $thisFile->item_type = $this->itemType;
                $thisFile->update();

                // Create default texts
                $translate = new sGalleryField();
                $translate->key = $thisFile->id;
                $translate->lang = evo()->getConfig('lang', 'base');
                $translate->save();

                // Response
                $data['success'] = 1;
                $data['message'] = 'Add Successfully!';
                $data['preview'] = $this->view('partials.youtube', ['gallery' => $thisFile, 'sGalleryController' => $this])->render();
            } else {
                // Response
                $data['success'] = 2;
                $data['message'] = 'Video not added.';
            }
        }
        return response()->json($data);
    }

    /**
     * Add a EVO library file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadEvoLibrary(Request $request)
    {
        $data = [];

        $validator = Validator::make($request->all(), [
            'cat' => 'required|integer|min:1',
            'file' => 'required|string'
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first(); // Error response
        } else {
            $filename = trim($request->input('file'), '/');
            $file = EVO_BASE_PATH . $filename;
            if (file_exists($file)) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = (string)$finfo->file($file);
                [$type, $subtype] = array_pad(explode('/', $mimeType, 2), 2, '');
                $filetype = $type;

                if ($filetype === 'application' && $subtype !== '') {
                    $filetype = $subtype;
                }

                // Save in DB
                $thisFile = sGalleryModel::whereParent($request->cat)
                    ->whereBlock($this->blockName)
                    ->whereItemType($this->itemType)
                    ->whereFile($filename)
                    ->firstOrCreate();
                $thisFile->parent = $request->cat;
                $thisFile->block = $this->blockName;
                $thisFile->file = $filename;
                $thisFile->type = $filetype;
                $thisFile->item_type = $this->itemType;
                $thisFile->update();

                // Create default texts
                $translate = new sGalleryField();
                $translate->key = $thisFile->id;
                $translate->lang = evo()->getConfig('lang', 'base');
                $translate->save();

                // Response
                $data['success'] = 1;
                $data['message'] = 'Add Successfully!';
                $data['preview'] = $this->view('partials.'.$filetype, ['gallery' => $thisFile, 'sGalleryController' => $this])->render();
            } else {
                // Response
                $data['success'] = 2;
                $data['message'] = 'File not added.';
            }
        }
        return response()->json($data);
    }

    /**
     * Sort the galleries based on the request data
     *
     * @param Request $request The current HTTP request
     * @return void
     */
    public function resortGallery(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'cat' => 'required|integer|min:1',
            'item' => 'required|array'
        ]);

        if ($validator->fails()) {
            Log::error('sGallery->resortGallery(): ' . $validator->errors()->first());
        } else {
            $items = implode('", "', $request->item);
            $galleries = sGalleryModel::whereParent($request->cat)
                ->whereBlock($this->blockName)
                ->whereItemType($this->itemType)
                ->orderByRaw('FIELD(id, "'.$items.'")')
                ->get();
            foreach ($galleries as $position => $gallery) {
                $gallery->position = $position;
                $gallery->update();
            }
        }
    }

    /**
     * Get fields from file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTranslate(Request $request)
    {
        $data = array();

        $validator = Validator::make($request->all(), [
            'item' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first(); // Error response
        } else {
            $items = sGalleryField::where('key', $request->item)->get()->mapWithKeys(function ($item, $key) {
                return [$item->lang => $item];
            });

            $data['success'] = 1;
            $data['message'] = 'Get Successfully!';
            $data['tabs'] = $this->view('partials.tabs', ['items' => $items, 'key' => $request->item])->render();
        }
        return response()->json($data);
    }

    /**
     * Save fields from file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setTranslate(Request $request)
    {
        $data = array();

        $validator = Validator::make($request->all(), [
            'list' => 'required|array'
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first(); // Error response
        } else {
            $key = array_key_first($request->list);
            $items = $request->list[$key];

            foreach ($items as $lang => $item) {
                $translate = sGalleryField::where('key', $key)->where('lang', $lang)->firstOrCreate();
                $translate->key = $key;
                $translate->lang = $lang;
                $translate->alt = ($item['alt'] ?? '');
                $translate->title = ($item['title'] ?? '');
                $translate->description = ($item['description'] ?? '');
                $translate->link_text = ($item['link_text'] ?? '');
                $translate->link = ($item['link'] ?? '');
                $translate->save();
            }

            $data['success'] = 1;
            $data['message'] = __('sGallery::manager.saved_successfully');
        }
        return response()->json($data);
    }

    /**
     * Delete item and fields
     *
     * @param Request $request
     * @return void
     */
    public function delete(Request $request)
    {
        $gallery = sGalleryModel::find((int)$request->item);
        if ($gallery) {
            sGalleryField::where('key', $gallery->id)->delete();
            $file = sGalleryModel::UPLOAD . $this->itemType . '/' . $gallery->parent . '/' . $gallery->file;
            if (file_exists($file)) {
                unlink($file);
            }
            $gallery->delete();
            $data['success'] = 1;
            $data['message'] = __('sGallery::manager.deleted_successfully');
        } else {
            $data['success'] = 0;
            $data['message'] = 'Item not found';
        }

        return response()->json($data);
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
        return \View::make('sGallery::'.$tpl, $data);
    }

    /**
     * Get the maximum allowed file size for uploads
     *
     * This method calculates the maximum file size allowed for uploads based on two factors:
     * 1. The value of the 'upload_max_filesize' PHP configuration directive, converted from Megabytes to kilobytes.
     * 2. The value of the 'upload_maxsize' configuration option in the application's configuration, converted from bytes to kilobytes.
     *
     * If the calculated maximum file size based on these factors is less than the value of 'upload_maxsize',
     * then it returns the calculated value, otherwise it returns the value of 'upload_maxsize'.
     *
     * @return int The maximum allowed file size for uploads in kilobytes
     */
    protected function maxsize()
    {
        $upload_max_filesize = intval(ini_get('upload_max_filesize')) * 1024;
        $upload_maxsize = evo()->getConfig('upload_maxsize', (intval('2M') * 1024 * 1024)) / 1024;
        return $upload_max_filesize < $upload_maxsize ? $upload_max_filesize : $upload_maxsize;
    }
}