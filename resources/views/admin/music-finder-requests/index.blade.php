@extends('layouts.admin')

@section('title', 'درخواست‌های آهنگ‌یاب')
@section('page_title', 'درخواست‌های شناسایی آهنگ')

@section('content')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">کل تحویل‌ها</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">از سرویس خارجی (هزینه‌دار)</p>
            <p class="text-2xl font-bold text-rose-600 mt-1">{{ number_format($stats['external_total']) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">از دیتابیس خودمون</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($stats['database_total']) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">امروز / ۲۴ ساعت اخیر / ۷ روز اخیر</p>
            <p class="text-lg font-bold text-gray-900 mt-1">
                {{ number_format($stats['today']) }} / {{ number_format($stats['last_24h']) }} /
                {{ number_format($stats['last_7d']) }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-4">
            {{-- فیلتر --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                <form method="GET" action="{{ route('admin.music-finder-requests.index') }}"
                    class="flex flex-wrap gap-4 items-end">
                    <div class="min-w-[160px]">
                        <label class="block text-xs text-gray-500 mb-1">پلتفرم</label>
                        <select name="platform"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm bg-white">
                            <option value="">همه</option>
                            <option value="telegram" @selected($platform === 'telegram')>تلگرام</option>
                            <option value="bale" @selected($platform === 'bale')>بله</option>
                        </select>
                    </div>
                    <div class="min-w-[160px]">
                        <label class="block text-xs text-gray-500 mb-1">منبع</label>
                        <select name="source" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm bg-white">
                            <option value="">همه</option>
                            <option value="database" @selected($source === 'database')>دیتابیس خودمون</option>
                            <option value="external_service" @selected($source === 'external_service')>سرویس خارجی</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[160px]">
                        <label class="block text-xs text-gray-500 mb-1">شناسه کاربر (user_id)</label>
                        <input type="text" name="user_id" value="{{ $userId }}" placeholder="مثلاً 123456789"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm">
                    </div>
                    <button type="submit"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">🔍
                        اعمال</button>
                    <a href="{{ route('admin.music-finder-requests.index') }}"
                        class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">پاک
                        کردن</a>
                </form>
            </div>

            {{-- جدول --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="text-right py-3 px-5 text-gray-500 font-medium">کاربر</th>
                                <th class="text-right py-3 px-5 text-gray-500 font-medium">پلتفرم</th>
                                <th class="text-right py-3 px-5 text-gray-500 font-medium">منبع</th>
                                <th class="text-right py-3 px-5 text-gray-500 font-medium">آهنگ / متن</th>
                                <th class="text-right py-3 px-5 text-gray-500 font-medium">تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $item)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-5">
                                        @php $bot = $userNames->get($item->platform . ':' . $item->user_id); @endphp
                                        <a href="{{ route('admin.music-finder-requests.index', ['user_id' => $item->user_id]) }}"
                                            class="block hover:underline">
                                            <span class="font-medium text-gray-800">{{ $bot?->full_name ?? 'ناشناس' }}</span>
                                            @if($bot?->username)
                                                <span class="text-xs text-gray-400" dir="ltr">@{{ $bot->username }}</span>
                                            @endif
                                            <span class="block font-mono text-[11px] text-gray-400">{{ $item->user_id }}</span>
                                        </a>
                                    </td>
                                    <td class="py-3 px-5">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $item->platform === 'bale' ? 'bg-indigo-100 text-indigo-800' : 'bg-sky-100 text-sky-800' }}">
                                            {{ $item->platform_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-5">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $item->source === 'external_service' ? 'bg-rose-100 text-rose-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $item->source_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-5 max-w-[280px] truncate">
                                        @if($item->song)
                                            <a href="https://music.becharkh.com/songs/{{ $item->song->slug }}" target="_blank"
                                                class="text-indigo-600 hover:underline">
                                                {{ $item->song->title }} — {{ $item->song->artist_name }}
                                            </a>
                                        @else
                                            {{ $item->query_text ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="py-3 px-5 text-gray-500 whitespace-nowrap">{{ jdate($item->created_at)->ago() }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-gray-400">موردی یافت نشد.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($requests->hasPages())
                    <div class="px-5 py-4 border-t border-gray-200">{{ $requests->links() }}</div>
                @endif
            </div>
        </div>

        {{-- پرمصرف‌ترین کاربرا --}}
        <div class="space-y-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-bold text-gray-900 mb-4">پرمصرف‌ترین‌ها (۲۴ ساعت اخیر)</h3>
                <ul class="space-y-2">
                    @forelse($topUsers24h as $u)
                        <li class="flex items-center justify-between text-sm">
                            <a href="{{ route('admin.music-finder-requests.index', ['user_id' => $u->user_id]) }}"
                                class="text-gray-700 hover:underline">
                                <span class="font-medium">{{ $u->display_name ?? 'ناشناس' }}</span>
                                <span class="text-xs text-gray-400">({{ $u->platform === 'bale' ? 'بله' : 'تلگرام' }})</span>
                            </a>
                            <span class="font-bold text-indigo-600">{{ $u->total }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">داده‌ای نیست.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-bold text-gray-900 mb-4">پرمصرف‌ترین‌ها (۷ روز اخیر)</h3>
                <ul class="space-y-2">
                    @forelse($topUsers7d as $u)
                        <li class="flex items-center justify-between text-sm">
                            <a href="{{ route('admin.music-finder-requests.index', ['user_id' => $u->user_id]) }}"
                                class="text-gray-700 hover:underline">
                                <span class="font-medium">{{ $u->display_name ?? 'ناشناس' }}</span>
                                <span class="text-xs text-gray-400">({{ $u->platform === 'bale' ? 'بله' : 'تلگرام' }})</span>
                            </a>
                            <span class="font-bold text-indigo-600">{{ $u->total }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">داده‌ای نیست.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

@endsection