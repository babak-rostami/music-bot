<?php

use App\Http\Controllers\BaleBotController;
use App\Http\Controllers\TelBotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// روت وبهوک تلگرام
Route::post('music-webhook', [TelBotController::class, 'index']);
// روت وبهوک بله
Route::post('bale-webhook', [BaleBotController::class, 'index']);
