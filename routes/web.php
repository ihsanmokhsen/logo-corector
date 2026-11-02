<?php

use App\Http\Controllers\LogoCorrectorController;
use Illuminate\Support\Facades\Route;

Route::controller(LogoCorrectorController::class)->group(function () {
    Route::get('/', 'index')->name('home');
    Route::get('/logo-resmi', fn () => response()->file(config('logocorrector.logo')))->name('logo');

    Route::post('/gambar', 'upload')->name('upload');
    Route::post('/gambar/{id}/deteksi', 'detect')->name('detect');
    Route::post('/gambar/{id}/ganti', 'replace')->name('replace');
    Route::get('/gambar/{id}/{type}', 'file')->whereIn('type', ['original', 'result'])->name('file');
    Route::get('/gambar/{id}/unduh', 'download')->name('download');
});
