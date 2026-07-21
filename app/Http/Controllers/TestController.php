<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSongDownloadLinks;
use App\Models\Song;
use App\Services\SongFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TestController extends Controller
{
    public function index()
    {
        $song = Song::find(39748);
        dd($song->downloadLinks);
    }
}
