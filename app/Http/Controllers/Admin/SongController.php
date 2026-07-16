<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SongSlugHelper;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use App\Models\SongDownloadLink;
use App\Services\ArtistResolver;
use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SongController extends Controller
{
    public function index(Request $request)
    {
        // ۱. بررسی اینکه آیا شناسه خواننده (artist_id) در درخواست وجود دارد یا خیر
        if ($request->filled('artist_id')) {
            $artistId = $request->input('artist_id');
            $currentArtist = Artist::find($artistId);

            // طبق خواسته شما: در صورت انتخاب خواننده، جستجوی الستیک اعمال نمی‌شود
            // فقط آهنگ‌های همین خواننده با همان صفحه‌بندی لیست می‌شوند
            $songs = Song::whereHas('artists', function ($q) use ($artistId) {
                $q->where('artists.id', $artistId);
            })->latest()->paginate(15)->withQueryString();

            return view('admin.songs.index', compact('songs', 'currentArtist'));
        }

        // ۲. اگر خواننده‌ای انتخاب نشده بود و کلمه کلیدی جستجو (search) وجود داشت -> الستیک سرچ
        if ($search = $request->input('search')) {
            $collection = Song::searchElastic($search);
            $perPage = 15;
            $page = request()->get('page', 1);

            $songs = new LengthAwarePaginator(
                $collection->forPage($page, $perPage),
                $collection->count(),
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );

            return view('admin.songs.index', compact('songs'));
        }

        // ۳. حالت پیش‌فرض: نمایش جدیدترین آهنگ‌ها بدون فیلتر و جستجو
        $songs = Song::latest()->paginate(15)->withQueryString();

        return view('admin.songs.index', compact('songs'));
    }

    public function create()
    {
        return view('admin.songs.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'                => ['required', 'string', 'max:255'],
            'artist_name'          => ['required', 'string', 'max:255'],
            'source_url'           => ['required', 'url', 'max:500', 'unique:songs'],
            'lyrics'               => ['nullable', 'string'],
            'downloads_count'      => ['nullable', 'integer', 'min:0'],
            'telegram_file_id_128' => ['nullable', 'string', 'max:255'],
            'telegram_file_id_320' => ['nullable', 'string', 'max:255'],
            'download_link_128'    => ['nullable', 'url', 'max:500'],
            'download_link_320'    => ['nullable', 'url', 'max:500'],
            'aparat_link'          => ['nullable'],
        ]);

        $slugSource = $validated['title'] . ' ' . $validated['artist_name'];
        $validated['slug'] = SongSlugHelper::make($slugSource);

        $song = Song::create($validated);

        Elasticsearch::sync($song);
        ArtistResolver::attachSingleArtist($song);

        $domain = $this->getSiteDomain($validated['source_url']);
        $this->storeDownloadLink($song, '128', $domain, $validated['download_link_128']);
        $this->storeDownloadLink($song, '320', $domain, $validated['download_link_320']);

        return redirect()->route('admin.songs.index')->with('success', 'آهنگ با موفقیت ایجاد شد.');
    }

    private function storeDownloadLink(Song $song, string $quality, $provider, ?string $downloadUrl): void
    {
        if (!$downloadUrl) {
            return;
        }

        SongDownloadLink::query()->firstOrCreate(
            [
                'provider' => $provider,
                'download_url' => $downloadUrl,
            ],
            [
                'song_id' => $song->id,
                'quality' => $quality,
            ]
        );
    }
    private function getSiteDomain($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        $domain = str_replace('www.', '', $host);
        return $domain;
    }
    public function edit(Song $song)
    {
        return view('admin.songs.edit', compact('song'));
    }

    public function update(Request $request, Song $song)
    {
        $validated = $request->validate([
            'title'                => ['required', 'string', 'max:255'],
            'artist_name'          => ['required', 'string', 'max:255'],
            'source_url'           => ['nullable', 'url', 'max:500', 'unique:songs,source_url,' . $song->id],
            'lyrics'               => ['nullable', 'string'],
            'downloads_count'      => ['nullable', 'integer', 'min:0'],
            'telegram_file_id_128' => ['nullable', 'string', 'max:255'],
            'telegram_file_id_320' => ['nullable', 'string', 'max:255'],
            'download_link_128'    => ['nullable', 'url', 'max:500'],
            'download_link_320'    => ['nullable', 'url', 'max:500'],
            'aparat_link'          => ['nullable'],
        ]);

        $song->update($validated);

        $changes = array_intersect_key(
            $song->getChanges(),
            array_flip(['title', 'lyrics', 'artist_name'])
        );

        if ($changes) {
            Elasticsearch::update($song, $changes);
        }

        $domain = $this->getSiteDomain($validated['source_url']);
        $this->storeDownloadLink($song, '128', $domain, $validated['download_link_128']);
        $this->storeDownloadLink($song, '320', $domain, $validated['download_link_320']);


        return redirect()->route('admin.songs.index')->with('success', 'آهنگ با موفقیت بروزرسانی شد.');
    }

    public function destroy(Song $song)
    {
        $song->delete();
        Elasticsearch::delete($song);

        return redirect()
            ->route('admin.songs.index')
            ->with('success', 'آهنگ با موفقیت حذف شد.');
    }
}
