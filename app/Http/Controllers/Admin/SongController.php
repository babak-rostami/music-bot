<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SongSlugHelper;
use App\Http\Controllers\Controller;
use App\Models\Song;
use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SongController extends Controller
{
    public function index(Request $request)
    {
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
        } else {

            $songs = Song::latest()
                ->paginate(15)
                ->withQueryString();
        }

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

        return redirect()->route('admin.songs.index')->with('success', 'آهنگ با موفقیت ایجاد شد.');
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

        return redirect()->route('admin.songs.index')->with('success', 'آهنگ با موفقیت بروزرسانی شد.');
    }
}
