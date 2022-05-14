<?php
use Illuminate\Support\Facades\Route;

Route::get('sgallery', function () {

    return \View::make('sGallery::index', ['data'=>'1']);

});