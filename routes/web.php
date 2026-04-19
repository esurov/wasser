<?php

use App\Http\Controllers\FountainPhotoController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'map')->name('home');

Route::get('/fountains/{objectId}/photos', [FountainPhotoController::class, 'index'])
    ->whereNumber('objectId');

Route::post('/fountains/{objectId}/photos', [FountainPhotoController::class, 'store'])
    ->whereNumber('objectId')
    ->middleware('throttle:10,1');
