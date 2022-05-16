<?php namespace Seiger\sGallery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Seiger\sGallery\Models\sGalleryModel;

class sGalleryController
{
    public function index()
    {
        $cat = request()->id ?? 0;
        $galleries = sGalleryModel::whereParent($cat)->get();
        return $this->view('index', ['galleries' => $galleries]);
    }

    public function uploadFile(Request $request)
    {
        $data = array();

        $validator = Validator::make($request->all(), [
            'cat' => 'required|integer|min:1',
            'file' => 'required|mimes:'.evo()->getConfig('upload_images', 'png,jpg,jpeg').'|max:'.evo()->getConfig('upload_maxsize', '2048')
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first('file'); // Error response
        } else {
            if ($request->file('file')) {
                $file = $request->file('file');
                $filename = $file->getClientOriginalName();

                // Upload file
                $file->move(sGalleryModel::UPLOAD.$request->cat, $filename);

                // Save in DB
                $thisFile = sGalleryModel::whereParent($request->cat)->whereFile($filename)->firstOrCreate();
                $thisFile->file = $filename;
                $thisFile->parent = $request->cat;
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