<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SongSlugHelper;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use App\Models\SongDownloadLink;
use App\Services\ArtistResolver;
use App\Services\ImageUploadService;
use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class SongController extends Controller
{
    public function __construct(
        private readonly ImageUploadService $images,
    ) {}

    /**
     * تنظیمات پردازش کاور — یک‌جا تعریف شده تا store و update دقیقاً یکسان باشند.
     *
     * @param  string  $filename  نام فایل بدون پسوند (اسلاگ آهنگ)
     * @return array<string,mixed>
     */
    private function coverOptions(string $filename): array
    {
        return [
            'width'        => 400,        // عرض ۳۰۰ پیکسل، ارتفاع خودکار
            'format'       => 'webp',     // تبدیل به webp
            'quality'      => 90,
            'crop'         => false,      // بدون برش، نسبت تصویر حفظ می‌شود
            'date_folders' => true,      // 👈 بدون زیرپوشه Y/m — مستقیم در music
            'filename'     => $filename,  // 👈 نام بر اساس اسلاگ آهنگ
        ];
    }

    /** پوشه‌ی مقصد روی دیسک. */
    private const COVER_DIRECTORY = '';

    public function index(Request $request)
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $allowedSorts = ['created_at', 'likes_count', 'dislikes_count', 'downloads_count'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $allowedDirections = ['asc', 'desc'];
        if (!in_array($sortDirection, $allowedDirections, true)) {
            $sortDirection = 'desc';
        }

        $perPage = 25;

        // ۱. فیلتر بر اساس خواننده
        if ($request->filled('artist_id')) {
            $artistId = $request->input('artist_id');
            $currentArtist = Artist::find($artistId);

            $songs = Song::whereHas('artists', function ($q) use ($artistId) {
                $q->where('artists.id', $artistId);
            })
                ->orderBy($sortBy, $sortDirection)
                ->paginate($perPage)
                ->withQueryString();

            return view('admin.songs.index', compact('songs', 'currentArtist', 'sortBy', 'sortDirection'));
        }

        // ۲. جستجوی الاستیک
        if ($search = $request->input('search')) {
            $collection = Song::searchElastic($search)
                ->sortBy($sortBy, SORT_REGULAR, $sortDirection === 'desc')
                ->values();

            $perPageCurrent = $perPage;
            $page = $request->get('page', 1);

            $songs = new LengthAwarePaginator(
                $collection->forPage($page, $perPageCurrent),
                $collection->count(),
                $perPageCurrent,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return view('admin.songs.index', compact('songs', 'sortBy', 'sortDirection'));
        }

        // ۳. حالت پیش‌فرض
        $songs = Song::orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.songs.index', compact('songs', 'sortBy', 'sortDirection'));
    }

    public function create()
    {
        return view('admin.songs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'                => ['required', 'string', 'max:255'],
            'artist_name'          => ['required', 'string', 'max:255'],
            'source_url'           => ['required', 'url', 'max:500', 'unique:songs'],
            'lyrics'               => ['nullable', 'string'],
            'downloads_count'      => ['nullable', 'integer', 'min:0'],
            'telegram_file_id_128' => ['nullable', 'string', 'max:255'],
            'telegram_file_id_320' => ['nullable', 'string', 'max:255'],
            'download_link_128'    => ['nullable', 'max:500'],
            'download_link_320'    => ['nullable', 'max:500'],
            'aparat_link'          => ['nullable'],
            'cover'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'show_in_saz_site' => ['nullable'],
            'description' => ['nullable']
        ]);

        // اسلاگ را قبل از آپلود می‌سازیم، چون نام فایل کاور از روی آن ساخته می‌شود
        $slug = SongSlugHelper::make($validated['title'] . ' ' . $validated['artist_name']);

        $downloadLink128 = $validated['download_link_128'] ?? null;
        $downloadLink320 = $validated['download_link_320'] ?? null;

        unset(
            $validated['cover']
        );

        $validated['slug'] = $slug;

        // آپلود کاور
        $coverPath = null;

        if ($request->hasFile('cover')) {
            // خروجی: music/shadmehr-aghili-dadad.webp
            $coverPath = $this->images->upload(
                $request->file('cover'),
                self::COVER_DIRECTORY,
                $this->coverOptions($slug)
            );

            $validated['cover_path'] = $coverPath;
        }

        try {
            $song = Song::create($validated);
        } catch (Throwable $e) {
            // اگر ذخیره در دیتابیس شکست خورد، فایل یتیم روی FTP باقی نماند
            $this->images->delete($coverPath);

            throw $e;
        }

        Elasticsearch::sync($song);
        ArtistResolver::attachSingleArtist($song);

        $domain = $this->getSiteDomain($song->source_url);
        $this->storeDownloadLink($song, '128', $domain, $downloadLink128);
        $this->storeDownloadLink($song, '320', $domain, $downloadLink320);

        return redirect()
            ->route('admin.songs.index')
            ->with('success', 'آهنگ با موفقیت ایجاد شد.');
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

    public function update(Request $request, Song $song): RedirectResponse
    {
        $validated = $request->validate([
            'title'                => ['required', 'string', 'max:255'],
            'artist_name'          => ['required', 'string', 'max:255'],
            'source_url'           => ['nullable', 'url', 'max:500', 'unique:songs,source_url,' . $song->id],
            'lyrics'               => ['nullable', 'string'],
            'downloads_count'      => ['nullable', 'integer', 'min:0'],
            'telegram_file_id_128' => ['nullable', 'string', 'max:255'],
            'telegram_file_id_320' => ['nullable', 'string', 'max:255'],
            'bale_file_id_128'     => ['nullable', 'string', 'max:255'],
            'bale_file_id_320'     => ['nullable', 'string', 'max:255'],
            'download_link_128'    => ['nullable', 'max:500'],
            'download_link_320'    => ['nullable', 'max:500'],
            'aparat_link'          => ['nullable'],
            'cover'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'show_in_saz_site' => ['nullable'],
            'description' => ['nullable']
        ]);

        $downloadLink128 = $validated['download_link_128'] ?? null;
        $downloadLink320 = $validated['download_link_320'] ?? null;

        unset(
            $validated['cover']
        );

        // --- کاور ---
        if ($request->hasFile('cover')) {
            $oldCoverPath = $song->cover_path;

            $validated['cover_path'] = $this->images->replace(
                $request->file('cover'),
                $oldCoverPath,              // اگر پر باشد، دقیقاً همین مسیر بازنویسی می‌شود
                self::COVER_DIRECTORY,
                $this->coverOptions($song->slug)
            );
        }

        $song->update($validated);

        $changes = array_intersect_key(
            $song->getChanges(),
            array_flip(['title', 'lyrics', 'artist_name'])
        );

        if ($changes) {
            Elasticsearch::update($song, $changes);
        }

        if (! empty($song->source_url)) {
            $domain = $this->getSiteDomain($song->source_url);
            $this->storeDownloadLink($song, '128', $domain, $downloadLink128);
            $this->storeDownloadLink($song, '320', $domain, $downloadLink320);
        }

        return redirect()
            ->route('admin.songs.index')
            ->with('success', 'آهنگ با موفقیت بروزرسانی شد.');
    }

    public function destroy(Song $song)
    {
        $this->images->delete($song->cover_path);

        $song->delete();
        Elasticsearch::delete($song);

        return redirect()
            ->route('admin.songs.index')
            ->with('success', 'آهنگ با موفقیت حذف شد.');
    }
}
