<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotUser;
use App\Models\MusicFinderRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MusicFinderRequestController extends Controller
{
    public function index(Request $request): View
    {
        $platform = $request->input('platform');
        $source = $request->input('source');
        $userId = $request->input('user_id');

        $query = MusicFinderRequest::query()->with('song')->latest();

        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($source) {
            $query->where('source', $source);
        }
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $requests = $query->paginate(30)->withQueryString();

        $stats = [
            'total'          => MusicFinderRequest::count(),
            'external_total' => MusicFinderRequest::where('source', MusicFinderRequest::SOURCE_EXTERNAL)->count(),
            'database_total' => MusicFinderRequest::where('source', MusicFinderRequest::SOURCE_DATABASE)->count(),
            'today'          => MusicFinderRequest::whereDate('created_at', today())->count(),
            'last_24h'       => MusicFinderRequest::where('created_at', '>=', now()->subHours(24))->count(),
            'last_7d'        => MusicFinderRequest::where('created_at', '>=', now()->subDays(7))->count(),
            'unique_users'   => MusicFinderRequest::query()->select('platform', 'user_id')->distinct()->get()->count(),
        ];

        $topUsers24h = $this->topUsersWithNames(now()->subHours(24));
        $topUsers7d = $this->topUsersWithNames(now()->subDays(7));

        $userNames = $this->userNamesFor($requests->pluck('user_id')->merge($requests->pluck('platform'))->all(), $requests);

        return view('admin.music-finder-requests.index', compact(
            'requests',
            'stats',
            'topUsers24h',
            'topUsers7d',
            'platform',
            'source',
            'userId',
            'userNames'
        ));
    }

    private function topUsersWithNames($since)
    {
        $rows = MusicFinderRequest::query()
            ->selectRaw('platform, user_id, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('platform', 'user_id')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $botUsers = BotUser::query()
            ->whereIn('platform', $rows->pluck('platform')->unique())
            ->whereIn('user_id', $rows->pluck('user_id')->unique())
            ->get()
            ->keyBy(fn($u) => $u->platform . ':' . $u->user_id);

        return $rows->map(function ($row) use ($botUsers) {
            $bot = $botUsers->get($row->platform . ':' . $row->user_id);
            $row->display_name = $bot?->full_name;
            $row->username = $bot?->username;
            return $row;
        });
    }

    private function userNamesFor(array $ignored, $requests)
    {
        $pairs = $requests->map(fn($r) => ['platform' => $r->platform, 'user_id' => $r->user_id])->unique();

        $botUsers = BotUser::query()
            ->whereIn('platform', $pairs->pluck('platform')->unique())
            ->whereIn('user_id', $pairs->pluck('user_id')->unique())
            ->get()
            ->keyBy(fn($u) => $u->platform . ':' . $u->user_id);

        return $botUsers;
    }
}
