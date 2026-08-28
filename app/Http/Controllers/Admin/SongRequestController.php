<?php

namespace App\Http\Controllers\Admin;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Telegram\Services\TelegramService;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBaleMusicDownloadJob;
use App\Jobs\ProcessMusicDownloadJob;
use App\Models\Song;
use App\Models\SongRequest;
use Illuminate\Http\Request;

class SongRequestController extends Controller
{
    public function __construct(private TelegramService $telegramService, private BaleService $baleService) {}

    public function index(Request $request)
    {
        $query = SongRequest::query();

        // فیلتر وضعیت
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // جستجو
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('query', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%");
            });
        }

        $songRequests = $query->latest()->paginate(15)->withQueryString();

        // آمار
        $stats = [
            'all'        => SongRequest::count(),
            'pending'    => SongRequest::pending()->count(),
            'processing' => SongRequest::processing()->count(),
            'completed'  => SongRequest::completed()->count(),
            'rejected'   => SongRequest::rejected()->count(),
        ];

        return view('admin.song-requests.index', compact('songRequests', 'stats'));
    }

    public function show(SongRequest $songRequest)
    {
        return view('admin.song-requests.show', compact('songRequest'));
    }

    public function updateStatus(Request $request, SongRequest $songRequest)
    {
        $validated = $request->validate([
            'status'     => ['required', 'in:pending,processing,completed,rejected'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $admin_note_last = $songRequest->admin_note;

        $songRequest->update($validated);

        if ($admin_note_last != $request->admin_note) {
            if ($request->admin_note != null && $request->status == 'completed') {
                $song = Song::find($request->admin_note);
                if (isset($song)) {
                    if ($songRequest->platform == "telegram") {
                        ProcessMusicDownloadJob::dispatch(
                            $song->id,
                            $songRequest->chat_id,
                            null,
                        )->onQueue('download-music');
                        $this->telegramService->sendMessage(
                            $songRequest->chat_id,
                            '❤️آهنگی که میخواستی برات آپلود کردم'
                        );
                    } elseif ($songRequest->platform == "bale") {
                        ProcessBaleMusicDownloadJob::dispatch(
                            $song->id,
                            $songRequest->chat_id,
                            null,
                        )->onQueue('download-music');
                        $this->baleService->sendMessage(
                            $songRequest->chat_id,
                            '❤️آهنگی که میخواستی برات آپلود کردم'
                        );
                    }
                }
            }
        }

        $statusLabel = $songRequest->status_label;
        return redirect()->route('admin.song-requests.index')->with('success', "وضعیت درخواست به «{$statusLabel}» تغییر کرد.");
    }

    public function destroy(SongRequest $songRequest)
    {
        $songRequest->delete();

        return redirect()->route('admin.song-requests.index')->with('success', 'درخواست با موفقیت حذف شد.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:song_requests,id'],
        ]);

        $count = SongRequest::query()
            ->whereIn('id', $validated['ids'])
            ->delete();

        return redirect()
            ->route('admin.song-requests.index')
            ->with('success', "{$count} درخواست حذف شد.");
    }
}
