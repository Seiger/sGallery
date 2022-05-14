<?php

namespace Seiger\sGallery\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class sGalleryController
{
    const UPLOAD = MODX_BASE_PATH . "assets/images/sgallery/";
    const UPLOADED = MODX_BASE_URL . "assets/images/sgallery/";

    public function index()
    {
        return $this->view('index');
    }

    public function uploadFile(Request $request)
    {
        $data = array();

        $validator = Validator::make($request->all(), [
            'cat' => 'required|integer|min:1',
            'file' => 'required|mimes:png,jpg,jpeg,csv,txt,pdf|max:2048'
        ]);

        if ($validator->fails()) {
            $data['success'] = 0;
            $data['error'] = $validator->errors()->first('file');// Error response
        } else {
            if ($request->file('file')) {
                $file = $request->file('file');
                $filename = $file->getClientOriginalName();

                // File extension
                $extension = $file->getClientOriginalExtension();

                // Upload file
                $file->move(self::UPLOAD.$request->cat, $filename);

                // File path
                $filepath = self::UPLOADED.$filename;

                // Response
                $data['success'] = 1;
                $data['message'] = 'Uploaded Successfully!';
                $data['filepath'] = $filepath;
                $data['extension'] = $extension;
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