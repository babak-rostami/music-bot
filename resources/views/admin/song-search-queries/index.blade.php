@extends('layouts.admin')

@section('title', 'جستجوهای کاربران')

@section('page_title', 'مدیریت جستجوها')

@section('header_actions')
    <form method="POST" action="{{ route('admin.song-search-queries.destroy-low-count') }}" class="inline"
        onsubmit="return confirm('همه جستجوهایی که تعدادشان کمتر یا مساوی مقدار وارد شده است حذف شوند؟');">
        @csrf
        @method('DELETE')
        <div class="flex items-center gap-2">
            <label for="max_count" class="text-sm text-slate-600 whitespace-nowrap">حذف کم‌تکرار ≤</label>
            <input id="max_count" type="number" name="max_count" min="1" max="100" value="1"
                class="w-20 rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-[#00509d] focus:outline-none focus:ring-2 focus:ring-[#00509d]/20">
            <button type="submit"
                class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-medium text-rose-700 transition hover:bg-rose-100">
                پاک‌سازی
            </button>
        </div>
    </form>
@endsection

@section('content')
    <div class="space-y-6" dir="rtl">

        {{-- Stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">عبارت یکتا (فیلتر فعلی)</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-900">
                    {{ number_format($stats['total_unique']) }}
                </div>
                <div class="mt-1 text-xs text-slate-400">
                    مجموع دفعات: {{ number_format($stats['total_searches']) }}
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">میانگین تکرار</div>
                <div class="mt-2 text-3xl font-extrabold text-[#00509d]">
                    {{ number_format($stats['avg_count'], 1) }}
                </div>
                <div class="mt-1 text-xs text-slate-400">
                    بیشترین: {{ number_format($stats['max_count']) }}
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">امروز</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-900">
                    {{ number_format($stats['today_unique']) }}
                </div>
                <div class="mt-1 text-xs text-slate-400">
                    {{ number_format($stats['today_searches']) }} بار جستجو
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">۷ روز اخیر / فقط یک‌بار</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-900">
                    {{ number_format($stats['week_unique']) }}
                </div>
                <div class="mt-1 text-xs text-slate-400">
                    یک‌بار سرچ‌شده: {{ number_format($stats['never_repeated']) }}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-4">

                {{-- Filters --}}
                <form method="GET" action="{{ route('admin.song-search-queries.index') }}"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div class="xl:col-span-2">
                            <label for="filter-q" class="mb-1 block text-xs font-medium text-slate-500">جستجو در
                                عبارات</label>
                            <input id="filter-q" type="search" name="q" value="{{ $filters['q'] }}"
                                placeholder="مثلاً: محسن یگانه"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-[#00509d] focus:outline-none focus:ring-2 focus:ring-[#00509d]/20">
                        </div>

                        <div>
                            <label for="filter-sort" class="mb-1 block text-xs font-medium text-slate-500">مرتب‌سازی</label>
                            <select id="filter-sort" name="sort"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-[#00509d] focus:outline-none focus:ring-2 focus:ring-[#00509d]/20">
                                <option value="count_desc" @selected($filters['sort'] === 'count_desc')>بیشترین تکرار</option>
                                <option value="count_asc" @selected($filters['sort'] === 'count_asc')>کمترین تکرار</option>
                                <option value="recent" @selected($filters['sort'] === 'recent')>جدیدترین</option>
                                <option value="oldest" @selected($filters['sort'] === 'oldest')>قدیمی‌ترین</option>
                                <option value="alpha" @selected($filters['sort'] === 'alpha')>الفبا (صعودی)</option>
                                <option value="alpha_desc" @selected($filters['sort'] === 'alpha_desc')>الفبا (نزولی)</option>
                            </select>
                        </div>

                        <div>
                            <label for="filter-period" class="mb-1 block text-xs font-medium text-slate-500">بازه
                                زمانی</label>
                            <select id="filter-period" name="period"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-[#00509d] focus:outline-none focus:ring-2 focus:ring-[#00509d]/20">
                                <option value="all" @selected($filters['period'] === 'all')>همه</option>
                                <option value="today" @selected($filters['period'] === 'today')>امروز</option>
                                <option value="week" @selected($filters['period'] === 'week')>۷ روز اخیر</option>
                                <option value="month" @selected($filters['period'] === 'month')>۳۰ روز اخیر</option>
                            </select>
                        </div>

                        <div>
                            <label for="filter-min-count" class="mb-1 block text-xs font-medium text-slate-500">حداقل
                                تکرار</label>
                            <input id="filter-min-count" type="number" name="min_count" min="1"
                                value="{{ $filters['min_count'] }}" placeholder="مثلاً 5"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-[#00509d] focus:outline-none focus:ring-2 focus:ring-[#00509d]/20">
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <label for="filter-per-page" class="text-xs text-slate-500">تعداد در صفحه</label>
                            <select id="filter-per-page" name="per_page"
                                class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                                @foreach ([15, 25, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected((int) $filters['per_page'] === $size)>{{ $size }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.song-search-queries.index') }}"
                                class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                                پاک کردن فیلتر
                            </a>
                            <button type="submit"
                                class="rounded-xl bg-[#00509d] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#003f7a]">
                                اعمال فیلتر
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Table --}}
                <form method="POST" action="{{ route('admin.song-search-queries.bulk-destroy') }}" id="bulk-delete-form"
                    onsubmit="return confirm('موارد انتخاب‌شده حذف شوند؟');">
                    @csrf
                    @method('DELETE')

                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                            <div class="text-sm text-slate-600">
                                {{ number_format($queries->total()) }} مورد
                                @if ($filters['q'] !== '')
                                    برای «{{ $filters['q'] }}»
                                @endif
                            </div>

                            <button type="submit"
                                class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-medium text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50"
                                id="bulk-delete-btn" disabled>
                                حذف انتخاب‌شده‌ها
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 text-right font-medium">
                                            <input type="checkbox" id="select-all"
                                                class="h-4 w-4 rounded border-slate-300 text-[#00509d] focus:ring-[#00509d]">
                                        </th>
                                        <th class="px-4 py-3 text-right font-medium">#</th>
                                        <th class="px-4 py-3 text-right font-medium">عبارت جستجو</th>
                                        <th class="px-4 py-3 text-right font-medium">تعداد</th>
                                        <th class="px-4 py-3 text-right font-medium">سهم</th>
                                        <th class="px-4 py-3 text-right font-medium">آخرین جستجو</th>
                                        <th class="px-4 py-3 text-right font-medium">اولین ثبت</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($queries as $item)
                                        @php
                                            $share = $stats['total_searches'] > 0
                                                ? ($item->count / $stats['total_searches']) * 100
                                                : 0;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80">
                                            <td class="px-4 py-3">
                                                <input type="checkbox" name="ids[]" value="{{ $item->id }}"
                                                    class="row-check h-4 w-4 rounded border-slate-300 text-[#00509d] focus:ring-[#00509d]">
                                            </td>
                                            <td class="px-4 py-3 text-slate-400">
                                                {{ $queries->firstItem() + $loop->index }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-slate-900">
                                                    {{ $item->query }}
                                                </div>
                                                <div class="mt-0.5 text-xs text-slate-400">
                                                    ID: {{ $item->id }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span
                                                    class="inline-flex min-w-[3rem] items-center justify-center rounded-full bg-[#00509d]/10 px-2.5 py-1 text-xs font-bold text-[#00509d]">
                                                    {{ number_format($item->count) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 min-w-[140px]">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                        <div class="h-full rounded-full bg-[#00509d]"
                                                            style="width: {{ min(100, max(2, $share)) }}%"></div>
                                                    </div>
                                                    <span class="w-12 text-left text-xs text-slate-500">
                                                        {{ number_format($share, 1) }}%
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                                @if ($item->last_searched_at)
                                                    <div>{{ jdate($item->last_searched_at)->ago() }}</div>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                                {{ jdate($item->created_at)->ago() ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3">

                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-4 py-16 text-center">
                                                <div class="text-base font-semibold text-slate-700">موردی پیدا نشد</div>
                                                <div class="mt-1 text-sm text-slate-400">
                                                    فیلترها را تغییر دهید یا صبر کنید تا کاربران جستجو کنند.
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($queries->hasPages())
                            <div class="border-t border-slate-100 px-4 py-3">
                                {{ $queries->links() }}
                            </div>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Side panels --}}
            <div class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900">محبوب‌ترین جستجوها</h3>
                        <span class="text-xs text-slate-400">Top 8</span>
                    </div>

                    <ul class="space-y-3">
                        @forelse ($topQueries as $top)
                            <li class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-slate-800">
                                        {{ $loop->iteration }}. {{ $top->query }}
                                    </div>
                                    <div class="text-xs text-slate-400">
                                        {{ optional($top->last_searched_at)->diffForHumans() ?? '—' }}
                                    </div>
                                </div>
                                <span
                                    class="shrink-0 rounded-full bg-[#00509d]/10 px-2 py-0.5 text-xs font-bold text-[#00509d]">
                                    {{ number_format($top->count) }}
                                </span>
                            </li>
                        @empty
                            <li class="text-sm text-slate-400">هنوز داده‌ای نیست.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900">جدیدترین جستجوها</h3>
                        <span class="text-xs text-slate-400">Recent</span>
                    </div>

                    <ul class="space-y-3">
                        @forelse ($recentQueries as $recent)
                            <li class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-slate-800">
                                        {{ $recent->query }}
                                    </div>
                                    <div class="text-xs text-slate-400">
                                        {{ optional($recent->last_searched_at)->format('Y/m/d H:i') ?? '—' }}
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">
                                    {{ number_format($recent->count) }}
                                </span>
                            </li>
                        @empty
                            <li class="text-sm text-slate-400">هنوز داده‌ای نیست.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="rounded-2xl border border-[#00509d]/15 bg-[#f4f9ff] p-5">
                    <h3 class="text-sm font-bold text-[#00509d]">راهنمای سریع</h3>
                    <ul class="mt-3 space-y-2 text-xs leading-6 text-slate-600">
                        <li>• با فیلتر «حداقل تکرار» می‌توانی عبارات بی‌ارزش یک‌باره‌ای را جدا کنی.</li>
                        <li>• دکمه «پاک‌سازی» بالا، همه موارد با count کم را یکجا حذف می‌کند.</li>
                        <li>• از checkbox برای حذف چندتایی استفاده کن.</li>
                        <li>• ستون «سهم» نسبت هر عبارت به کل جستجوهای فیلتر فعلی است.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const selectAll = document.getElementById('select-all');
            const checks = () => Array.from(document.querySelectorAll('.row-check'));
            const bulkBtn = document.getElementById('bulk-delete-btn');

            function syncBulkBtn() {
                const any = checks().some((c) => c.checked);
                if (bulkBtn) bulkBtn.disabled = !any;
            }

            selectAll?.addEventListener('change', function () {
                checks().forEach((c) => {
                    c.checked = selectAll.checked;
                });
                syncBulkBtn();
            });

            document.addEventListener('change', function (e) {
                if (e.target.classList?.contains('row-check')) {
                    const all = checks();
                    if (selectAll) {
                        selectAll.checked = all.length > 0 && all.every((c) => c.checked);
                    }
                    syncBulkBtn();
                }
            });
        })();
    </script>
@endsection