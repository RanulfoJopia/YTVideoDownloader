<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YoutubeController;

Route::get('/', [YoutubeController::class, 'index'])->name('home');
Route::post('/scan', [YoutubeController::class, 'scan'])->name('yt.scan');
Route::post('/download', [YoutubeController::class, 'download'])->name('yt.download');