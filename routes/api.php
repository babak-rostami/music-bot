<?php

use App\Http\Controllers\CrawlMusicController;
use App\Http\Controllers\TelBotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('music-webhook', [TelBotController::class, 'index']);
Route::get('music', [CrawlMusicController::class, 'crawl']);
