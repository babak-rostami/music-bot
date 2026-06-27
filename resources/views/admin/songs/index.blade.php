@extends('layouts.admin')

@section('title', 'آهنگ‌ها')
@section('page_title', 'مدیریت آهنگ‌ها')

@section('header_actions')
    <span class="text-sm text-gray-500">کل: {{ $songs->total() }} آهنگ</span>
    <a href="{{ route('admin.songs.create') }}"
        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        آهنگ جدید
    </a>
@endsection

@section('content')
    <!-- جستجو -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('admin.songs.index') }}" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-xs text-gray-500 mb-1">جستجو</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="عنوان، خواننده، آدرس..."
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
            <button type="submit"
                class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                جستجو
            </button>
            <a href="{{ route('admin.songs.index') }}"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                پاک کردن
            </a>
        </form>
    </div>

    <!-- جدول آهنگ‌ها -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">شناسه</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">عنوان</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">خواننده</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">دانلود</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">متن</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">لینک ۱۲۸</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">لینک ۳۲۰</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">تاریخ</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($songs as $song)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-5 font-medium text-gray-900 max-w-[200px] truncate">{{ $song->id }}</td>
                            <td class="py-3 px-5 font-medium text-gray-900 max-w-[200px] truncate">{{ $song->title }}</td>
                            <td class="py-3 px-5 text-gray-600">{{ $song->artist_name }}</td>
                            <td class="py-3 px-5 text-gray-500">{{ number_format($song->downloads_count) }}</td>
                            <td class="py-3 px-5">
                                @if($song->lyrics)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-5">
                                @if($song->download_link_128)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-5">
                                @if($song->download_link_320)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-5 text-gray-500">{{ $song->created_at->format('Y/m/d') }}</td>
                            <td class="py-3 px-5">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.songs.edit', $song) }}" title="ویرایش"
                                        class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-400">هیچ آهنگی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($songs->hasPages())
            <div class="px-5 py-4 border-t border-gray-200">
                {{ $songs->withQueryString()->links() }}
            </div>
        @endif
    </div>
@endsection