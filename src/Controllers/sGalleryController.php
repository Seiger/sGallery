<?php namespace Seiger\sGallery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Seiger\sGallery\Models\sGalleryField;
use Seiger\sGallery\Models\sGalleryModel;
use sGallery;

class sGalleryController
{
    protected $viewType;
    protected $resourceType;
    protected $idType;

    /**
     * @param string $viewType tab or section or sectionFiles
     * @param string $resourceType resource
     * @param string $idType id
     */
    public function __construct(string $viewType = 'tab', string $resourceType = 'resource', string $idType = 'id')
    {
        if (in_array($viewType, [
            sGalleryModel::VIEW_TAB,
            sGalleryModel::VIEW_SECTION,
            sGalleryModel::VIEW_SECTION_DOWNLOADS
        ])) {
            $this->viewType = $viewType;
        } else {
            $this->viewType = 'tab';
        }

        if (request()->has('amp;resourceType')) {
            $resourceType = request()->get('amp;resourceType');
        } else {
            $resourceType = request()->resourceType ?? $resourceType;
        }
        $this->resourceType = trim($resourceType, '/');
        $this->idType = $idType;
    }

    /**
     * Show tab page with Gallery files
     *
     * @return View
     */
    public function index(): View
    {
        $cat = request()->{$this->idType} ?? 0;
        $galleries = sGalleryModel::whereParent($cat)->whereResourceType($this->resourceType)->orderBy('position')->get();
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
            'cat' => 'required|integer|min:1',
            'file' => 'required|mimes:'.evo()->getConfig('upload_images', 'png,jpg,jpeg,mp4').','.evo()->getConfig('upload_media', 'mp3,mp4').'|max:'.evo()->getConfig('upload_maxsize', '2048')
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

                // Upload file
                $file->move(sGalleryModel::UPLOAD.$this->resourceType.'/'.$request->cat, $filename);

                // Save in DB
                $thisFile = sGalleryModel::whereParent($request->cat)->whereResourceType($this->resourceType)->whereFile($filename)->firstOrCreate();
                $thisFile->parent = $request->cat;
                $thisFile->file = $filename;
                $thisFile->type = $filetype;
                $thisFile->resource_type = $this->resourceType;
                $thisFile->update();

                // Create default texts
                $translate = new sGalleryField();
                $translate->key = $thisFile->id;
                $translate->lang = evo()->getConfig('lang', 'base');
                $translate->save();

                // Response
                $data['success'] = 1;
                $data['message'] = 'Uploaded Successfully!';
                $data['preview'] = $this->view('partials.'.$filetype, ['gallery' => $thisFile])->render();
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadDownload(Request $request)
    {
        $data = [];

        $validator = Validator::make($request->all(), [
            'cat' => 'required|integer|min:1',
            'file' => 'required|mimes:'.evo()->getConfig('upload_files', 'odp,odt,pdf,ppt,pptx,doc,docx').'|max:'.evo()->getConfig('upload_maxsize', '2048')
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

                // Upload file
                $file->move(sGalleryModel::UPLOAD.$this->resourceType.'/'.$request->cat, $filename);

                // Save in DB
                $thisFile = sGalleryModel::whereParent($request->cat)->whereResourceType($this->resourceType)->whereFile($filename)->firstOrCreate();
                $thisFile->parent = $request->cat;
                $thisFile->file = $filename;
                $thisFile->type = $filetype;
                $thisFile->resource_type = $this->resourceType;
                $thisFile->update();

                // Create default texts
                $translate = new sGalleryField();
                $translate->key = $thisFile->id;
                $translate->lang = evo()->getConfig('lang', 'base');
                $translate->save();

                // Response
                $data['success'] = 1;
                $data['message'] = 'Uploaded Successfully!';
                $data['preview'] = $this->view('partials.'.$filetype, ['gallery' => $thisFile])->render();
            } else {
                // Response
                $data['success'] = 2;
                $data['message'] = 'File not uploaded.';
            }
        }

        return response()->json($data);
    }

    /**
     * Add YouTube video
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addYoutube(Request $request)
    {
        $data = [];

        $validator = Validator::make($request->all(), [
            'cat' => 'required|integer|min:1',
            'youtube_link' => 'required|active_url'
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first(); // Error response
        } else {
            if (preg_match('/[A-z0-9_-]{11}/', $request->youtube_link, $video)) {
                // Save in DB
                $thisFile = sGalleryModel::whereParent($request->cat)->whereResourceType($this->resourceType)->whereFile($video[0])->firstOrCreate();
                $thisFile->parent = $request->cat;
                $thisFile->file = $video[0];
                $thisFile->type = 'youtube';
                $thisFile->resource_type = $this->resourceType;
                $thisFile->update();

                // Create default texts
                $translate = new sGalleryField();
                $translate->key = $thisFile->id;
                $translate->lang = evo()->getConfig('lang', 'base');
                $translate->save();

                // Response
                $data['success'] = 1;
                $data['message'] = 'Add Successfully!';
                $data['preview'] = $this->view('partials.youtube', ['gallery' => $thisFile])->render();
            } else {
                // Response
                $data['success'] = 2;
                $data['message'] = 'Video not added.';
            }
        }
        return response()->json($data);
    }

    /**
     * Update sorting
     *
     * @param Request $request
     * @return void
     */
    public function sortGallery(Request $request): void
    {
        $data = array();

        $validator = Validator::make($request->all(), [
            'cat' => 'required|integer|min:1',
            'item' => 'required|array'
        ]);

        if (!$validator->fails()) {
            $items = implode('", "', $request->item);
            $galleries = sGalleryModel::whereParent($request->cat)->whereResourceType($this->resourceType)->orderByRaw('FIELD(id, "'.$items.'")')->get();
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
    public function delete(Request $request): void
    {
        $gallery = sGalleryModel::find((int)$request->item);
        if ($gallery) {
            sGalleryField::where('key', $gallery->id)->delete();
            unlink(sGalleryModel::UPLOAD . $this->resourceType . '/' . $gallery->parent . '/' . $gallery->file);
            $gallery->delete();
        }
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
    public function getResourceType()
    {
        return $this->resourceType;
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
}