@extends('layouts.admin')

@section('title', 'آهنگ‌ها')
@section('page_title', 'مدیریت آهنگ‌ها')

@section('header_actions')
    <span class="hidden sm:inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg">
        کل: {{ number_format($songs->total()) }} آهنگ
    </span>
    <a href="{{ route('admin.songs.create') }}"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        آهنگ جدید
    </a>
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- جستجو --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('admin.songs.index') }}" class="flex flex-wrap gap-4 items-end">

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">جستجو</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="عنوان، خواننده، آدرس..."
                        class="w-full pr-10 pl-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    <svg class="absolute right-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <button type="submit"
                class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                🔍 جستجو
            </button>

            <a href="{{ route('admin.songs.index') }}"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                پاک کردن
            </a>
        </form>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- جدول آهنگ‌ها --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">شناسه</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">عنوان</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">خواننده</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">دانلود</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">متن</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">۱۲۸</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">۳۲۰</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">تاریخ</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($songs as $song)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-5 font-mono text-gray-400 text-xs">{{ $song->id }}</td>
                            <td class="py-3 px-5 font-medium text-gray-900 max-w-[200px] truncate">{{ $song->title }}</td>
                            <td class="py-3 px-5 text-gray-600">{{ $song->artist_name }}</td>
                            <td class="py-3 px-5 text-gray-500 whitespace-nowrap">{{ number_format($song->downloads_count) }}
                            </td>

                            {{-- متن آهنگ --}}
                            <td class="py-3 px-5 text-center">
                                @if($song->lyrics)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            {{-- لینک ۱۲۸ --}}
                            <td class="py-3 px-5 text-center">
                                @if($song->download_link_128)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            {{-- لینک ۳۲۰ --}}
                            <td class="py-3 px-5 text-center">
                                @if($song->download_link_320)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            <td class="py-3 px-5 text-gray-500 whitespace-nowrap">
                                {{ jdate($song->created_at)->ago() }}
                            </td>

                            <td class="py-3 px-5">
                                <div class="flex items-center justify-center gap-2">
                                    {{-- نمایش --}}
                                    <a href="https://music.becharkh.com/songs/{{ $song->slug }}" target="_blank" rel="noopener"
                                        title="نمایش"
                                        class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7S3.732 16.057 2.458 12z" />
                                        </svg>
                                    </a>
                                    {{-- ویرایش --}}
                                    <a href="{{ route('admin.songs.edit', $song) }}" title="ویرایش"
                                        class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    {{-- حذف --}}
                                    <form action="{{ route('admin.songs.destroy', $song) }}" method="POST"
                                        onsubmit="return confirm('آیا از حذف این آهنگ مطمئن هستید؟');">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" title="حذف"
                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7L18.132 19.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.994-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-400">
                                <div class="text-4xl mb-2">🎵</div>
                                هیچ آهنگی یافت نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($songs->hasPages())
            <div class="px-5 py-4 border-t border-gray-200">
                {{ $songs->links() }}
            </div>
        @endif
    </div>

@endsection