<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ArtistController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\SongController;
use App\Http\Controllers\Admin\SongDownloadLinkController;
use App\Http\Controllers\Admin\SongRequestController;
use App\Http\Controllers\Admin\SongSearchQueryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\BotUserController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\MusicFinderRequestController;
use App\Http\Controllers\TestController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', [TestController::class, 'index']);


// ─── روت‌های ادمین ───────────────────────────────────────────
Route::prefix('admin_page')->name('admin.')->group(function () {

    // لاگین (بدون نیاز به احراز هویت)
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.send');

    // روت‌های محافظت‌شده
    Route::middleware([AdminMiddleware::class])->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // مدیریت کاربران
        Route::resource('users', UserController::class)->except(['create', 'store', 'show', 'destroy']);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');

        // مدیریت کاربران ربات
        Route::prefix('bot-users')->name('bot-users.')->controller(BotUserController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/{botUser}', 'show')->name('show');
            Route::post('/{botUser}/toggle-block', 'toggleBlock')->name('toggle-block');
            Route::post('/{botUser}/reset-failed', 'resetFailedCount')->name('reset-failed');
            // ارسال پیام و آهنگ به کاربر
            Route::post('/{botUser}/send-message', 'sendMessage')->name('send-message');
        });

        // مدیریت آهنگ‌ها
        Route::resource('songs', SongController::class);
        // نمایش لیست لینک‌های دانلود یک آهنگ
        Route::get('song-download-link/{id}', [SongDownloadLinkController::class, 'index'])
            ->name('song.download.link.index');
        Route::put('song-download-link/{downloadLink}', [SongDownloadLinkController::class, 'update'])
            ->name('song.download.link.update');
        Route::delete('song-download-link/{downloadLink}', [SongDownloadLinkController::class, 'destroy'])
            ->name('song.download.link.destroy');
        // تست لینک دانلود
        Route::post('test-link', [SongDownloadLinkController::class, 'testLink'])->name('test.link');

        // مسیرهای مدیریت خوانندگان (Artists)
        Route::resource('artists', ArtistController::class);

        // مدیریت درخواست‌های آپلود
        Route::delete('song-requests/bulk', [SongRequestController::class, 'bulkDestroy'])->name('song-requests.bulk-destroy');
        Route::resource('song-requests', SongRequestController::class)->except(['create', 'store', 'edit']);
        Route::post('song-requests/{songRequest}/update-status', [SongRequestController::class, 'updateStatus'])->name('song-requests.update-status');


        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
        Route::delete('activity-logs/{activityLog}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');
        Route::post('activity-logs/clear', [ActivityLogController::class, 'clear'])->name('activity-logs.clear');
        Route::get('activity-logs/stats/json', [ActivityLogController::class, 'stats'])->name('activity-logs.stats');

        Route::get('song-search-queries', [SongSearchQueryController::class, 'index'])->name('song-search-queries.index');
        Route::delete('song-search-queries/bulk', [SongSearchQueryController::class, 'bulkDestroy'])->name('song-search-queries.bulk-destroy');
        Route::delete('song-search-queries/low-count', [SongSearchQueryController::class, 'destroyLowCount'])->name('song-search-queries.destroy-low-count');
        Route::delete('song-search-queries/{songSearchQuery}', [SongSearchQueryController::class, 'destroy'])->name('song-search-queries.destroy');


        // مدیریت نظرات — bulk حتماً باید قبل از resource ثبت بشه
        Route::delete('comments/bulk', [CommentController::class, 'bulkDestroy'])->name('comments.bulk-destroy');
        Route::resource('comments', CommentController::class)->only(['index', 'update', 'destroy']);
        Route::post('comments/{comment}/update-status', [CommentController::class, 'updateStatus'])->name('comments.update-status');

        Route::get('music-finder-requests', [MusicFinderRequestController::class, 'index'])->name('music-finder-requests.index');
    });
});
