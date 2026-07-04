<?php

use App\Http\Controllers\CrawlMusicController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\TelBotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('music-webhook', [TelBotController::class, 'index']);

Route::get('crawl-musics', [CrawlMusicController::class, 'crawlAll']);
Route::get('crawl-last-musics', [CrawlMusicController::class, 'crawlLast']);

Route::get('crawl-song-again/{song}', [SongController::class, 'crawlSongAgain'])->name('crawl.song.again');
