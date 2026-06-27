<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\SongController;
use App\Http\Controllers\Admin\SongRequestController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AdminMiddleware;
use App\Models\Song;
use App\Models\SongRequest;
use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Symfony\Component\DomCrawler\Crawler;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/fix-crawl-info', function () {
    dd('start fixing crawl info');
    // تمام آهنگ‌ها را در دسته‌های ۱۰۰ تایی پردازش می‌کنیم تا حافظه پر نشود
    Song::chunk(100, function ($songs) {
        foreach ($songs as $song) {
            if (empty($song->lyrics) || $song->lyrics === '...' || trim($song->lyrics) === '') {
                $song->lyrics = null;
                $song->save();
            }
        }
    });
});

// ─── روت‌های ادمین ───────────────────────────────────────────
Route::prefix('admin_page')->name('admin.')->group(function () {

    // لاگین (بدون نیاز به احراز هویت)
    Route::get('/login_admin_ok', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.send');

    // روت‌های محافظت‌شده
    Route::middleware([AdminMiddleware::class])->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // مدیریت کاربران
        Route::resource('users', UserController::class)->except(['create', 'store', 'show', 'destroy']);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');

        // مدیریت آهنگ‌ها
        Route::resource('songs', SongController::class)->except(['destroy']);

        // مدیریت درخواست‌های آپلود
        Route::resource('song-requests', SongRequestController::class)->except(['create', 'store', 'edit']);
        Route::post('song-requests/{songRequest}/update-status', [SongRequestController::class, 'updateStatus'])->name('song-requests.update-status');
    });
});

route::get('test', function () {
    $songs = Song::where('telegram_file_id_128', '!=', null)
        ->where('telegram_file_id_320', '!=', null)->get();
    foreach ($songs as $song) {
        $song->telegram_file_id_128 = null;
        $song->telegram_file_id_320 = null;
        $song->save();
    }
});
