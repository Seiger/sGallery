<?php
use Illuminate\Support\Facades\Route;
use Seiger\sGallery\Controllers\sGalleryController;

Route::middleware('mgr')->group(function () {
    Route::get('sgallery', [sGalleryController::class, 'index']);
    Route::post('sgallery/upload-file', [sGalleryController::class, 'uploadFile'])->name('sGallery.upload-file');
    Route::post('sgallery/upload-download', [sGalleryController::class, 'uploadDownload'])->name('sGallery.upload-download');
    Route::get('sgallery/upload', [sGalleryController::class, 'addYoutube'])->name('sGallery.addyoutube');
    Route::post('sgallery/sort', [sGalleryController::class, 'sortGallery'])->name('sGallery.sort');
    Route::post('sgallery/delete', [sGalleryController::class, 'delete'])->name('sGallery.delete');
    Route::get('sgallery/translate', [sGalleryController::class, 'getTranslate'])->name('sGallery.gettranslate');
    Route::post('sgallery/translate', [sGalleryController::class, 'setTranslate'])->name('sGallery.settranslate');
});