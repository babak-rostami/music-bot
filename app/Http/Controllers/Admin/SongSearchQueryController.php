<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SongSearchQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SongSearchQueryController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'sort' => ['nullable', 'in:count_desc,count_asc,recent,oldest,alpha,alpha_desc'],
            'min_count' => ['nullable', 'integer', 'min:1'],
            'period' => ['nullable', 'in:all,today,week,month'],
            'per_page' => ['nullable', 'integer', 'in:15,25,50,100'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $sort = $validated['sort'] ?? 'count_desc';
        $minCount = $validated['min_count'] ?? null;
        $period = $validated['period'] ?? 'all';
        $perPage = (int) ($validated['per_page'] ?? 25);

        $baseQuery = SongSearchQuery::query();

        if ($search !== '') {
            $baseQuery->where('query', 'like', '%' . $search . '%');
        }

        if ($minCount) {
            $baseQuery->where('count', '>=', $minCount);
        }

        $this->applyPeriod($baseQuery, $period);

        $statsQuery = clone $baseQuery;
        $stats = [
            'total_unique' => (clone $statsQuery)->count(),
            'total_searches' => (int) (clone $statsQuery)->sum('count'),
            'avg_count' => round((float) (clone $statsQuery)->avg('count'), 1),
            'max_count' => (int) (clone $statsQuery)->max('count'),
            'today_unique' => SongSearchQuery::query()
                ->whereDate('last_searched_at', today())
                ->count(),
            'today_searches' => (int) SongSearchQuery::query()
                ->whereDate('last_searched_at', today())
                ->sum('count'),
            'week_unique' => SongSearchQuery::query()
                ->where('last_searched_at', '>=', now()->subDays(7))
                ->count(),
            'never_repeated' => SongSearchQuery::query()
                ->where('count', 1)
                ->count(),
        ];

        $topQueries = SongSearchQuery::query()
            ->orderByDesc('count')
            ->orderByDesc('last_searched_at')
            ->limit(8)
            ->get();

        $recentQueries = SongSearchQuery::query()
            ->orderByDesc('last_searched_at')
            ->limit(8)
            ->get();

        $listQuery = clone $baseQuery;
        $this->applySort($listQuery, $sort);

        $queries = $listQuery->paginate($perPage)->withQueryString();

        return view('admin.song-search-queries.index', [
            'queries' => $queries,
            'stats' => $stats,
            'topQueries' => $topQueries,
            'recentQueries' => $recentQueries,
            'filters' => [
                'q' => $search,
                'sort' => $sort,
                'min_count' => $minCount,
                'period' => $period,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function destroy(SongSearchQuery $songSearchQuery): RedirectResponse
    {
        $label = $songSearchQuery->query;
        $songSearchQuery->delete();

        return back()->with('success', "جستجوی «{$label}» حذف شد.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:song_search_queries,id'],
        ]);

        $count = SongSearchQuery::query()
            ->whereIn('id', $validated['ids'])
            ->delete();

        return back()->with('success', "{$count} مورد انتخاب‌شده حذف شد.");
    }

    public function destroyLowCount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'max_count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $deleted = SongSearchQuery::query()
            ->where('count', '<=', $validated['max_count'])
            ->delete();

        return back()->with(
            'success',
            "{$deleted} جستجو با تعداد ≤ {$validated['max_count']} حذف شد."
        );
    }

    protected function applyPeriod($query, string $period): void
    {
        match ($period) {
            'today' => $query->whereDate('last_searched_at', today()),
            'week' => $query->where('last_searched_at', '>=', now()->subDays(7)),
            'month' => $query->where('last_searched_at', '>=', now()->subDays(30)),
            default => null,
        };
    }

    protected function applySort($query, string $sort): void
    {
        match ($sort) {
            'count_asc' => $query->orderBy('count')->orderBy('query'),
            'recent' => $query->orderByDesc('last_searched_at')->orderByDesc('count'),
            'oldest' => $query->orderBy('last_searched_at')->orderBy('count'),
            'alpha' => $query->orderBy('query'),
            'alpha_desc' => $query->orderByDesc('query'),
            default => $query->orderByDesc('count')->orderByDesc('last_searched_at'),
        };
    }
}
