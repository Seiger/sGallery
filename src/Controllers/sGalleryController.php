<?php namespace Seiger\sGallery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Seiger\sGallery\Models\sGalleryModel;

class sGalleryController
{
    /**
     * Show tab page with Gallery files
     *
     * @return bool
     */
    public function index()
    {
        $cat = request()->id ?? 0;
        $galleries = sGalleryModel::whereParent($cat)->orderBy('position')->get();
        return $this->view('index', ['galleries' => $galleries]);
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
            'file' => 'required|mimes:'.evo()->getConfig('upload_files', 'png,jpg,jpeg,mp4').'|max:'.evo()->getConfig('upload_maxsize', '2048')
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first('file'); // Error response
        } else {
            if ($request->file('file')) {
                $file = $request->file('file');
                $filename = $file->getClientOriginalName();
                $filetype = explode('/', $file->getMimeType())[0];

                // Upload file
                $file->move(sGalleryModel::UPLOAD.$request->cat, $filename);

                // Save in DB
                $thisFile = sGalleryModel::whereParent($request->cat)->whereFile($filename)->firstOrCreate();
                $thisFile->parent = $request->cat;
                $thisFile->file = $filename;
                $thisFile->type = $filetype;
                $thisFile->update();

                // Response
                $data['success'] = 1;
                $data['message'] = 'Uploaded Successfully!';
                $data['preview'] = $this->view('partials.image', ['gallery' => $thisFile])->render();
            } else {
                // Response
                $data['success'] = 2;
                $data['message'] = 'File not uploaded.';
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
            $galleries = sGalleryModel::whereParent($request->cat)->orderByRaw('FIELD(id, "'.$items.'")')->get();
            foreach ($galleries as $position => $gallery) {
                $gallery->position = $position;
                $gallery->update();
            }
        }
    }

    /**
     * Display render
     *
     * @param $tpl
     * @param array $data
     * @return bool
     */
    public function view($tpl, $data = [])
    {
        return \View::make('sGallery::'.$tpl, $data);
    }
}