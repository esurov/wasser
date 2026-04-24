<?php

use App\Http\Controllers\FountainPhotoController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'map')->name('home');

Route::get('/stats', StatsController::class)->name('stats');

Route::get('/fountains/{shapeHash}/photos', [FountainPhotoController::class, 'index'])
    ->where('shapeHash', '[0-9a-f]{40}');

Route::post('/fountains/{shapeHash}/photos', [FountainPhotoController::class, 'store'])
    ->where('shapeHash', '[0-9a-f]{40}')
    ->middleware('throttle:10,1');
