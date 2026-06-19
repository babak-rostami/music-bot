<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlLastMusicJob;
use App\Jobs\CrawlMusicJob;
use App\Services\MusicCrawlerService;
use Illuminate\Support\Facades\Request;

class CrawlMusicController extends Controller
{
    private $musicCrawlerService;
    public function __construct(MusicCrawlerService $musicCrawlerService)
    {
        $this->musicCrawlerService = $musicCrawlerService;
    }
    public function crawlAll(Request $request)
    {
        dd('ok');
        $this->musicCrawlerService->startAll();

        return response()->json([
            'message' => 'Crawler started successfully.'
        ]);
    }

    public function crawlLast(Request $request)
    {
        dd('ok');

        CrawlLastMusicJob::dispatch()->onQueue('crawl-musics-page');

        return response()->json([
            'message' => 'Crawler started successfully.'
        ]);
    }
}
