<?php

use App\Models\Song;
use Babak\Elasticsearch\Facades\Elasticsearch;
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
