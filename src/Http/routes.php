<?php
use Illuminate\Support\Facades\Route;
use Seiger\sGallery\Controllers\sGalleryController;

Route::middleware('mgr')->group(function () {
    Route::get('sgallery', [sGalleryController::class, 'index']);
    Route::post('sgallery/upload', [sGalleryController::class, 'uploadFile'])->name('sGallery.upload');
    Route::get('sgallery/upload', [sGalleryController::class, 'addYoutube'])->name('sGallery.addyoutube');
    Route::post('sgallery/sort', [sGalleryController::class, 'sortGallery'])->name('sGallery.sort');
});