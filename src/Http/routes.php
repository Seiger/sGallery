<?php

use Illuminate\Support\Facades\Route;
use Seiger\sGallery\Controllers\sGalleryController;

Route::middleware('mgr')->prefix('sgallery/')->name('sGallery.')->group(function () {
    Route::get('', [sGalleryController::class, 'index']);
    Route::post('upload-file', [sGalleryController::class, 'uploadFile'])->name('upload-file');
    Route::post('upload-download', [sGalleryController::class, 'uploadDownload'])->name('upload-download');
    Route::post('upload', [sGalleryController::class, 'addYoutube'])->name('addyoutube');
    Route::post('sort', [sGalleryController::class, 'sortGallery'])->name('sort');
    Route::post('delete', [sGalleryController::class, 'delete'])->name('delete');
    Route::get('translate', [sGalleryController::class, 'getTranslate'])->name('gettranslate');
    Route::post('translate', [sGalleryController::class, 'setTranslate'])->name('settranslate');
});