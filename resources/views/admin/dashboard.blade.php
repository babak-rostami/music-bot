@extends('layouts.admin')

@section('title', 'داشبورد')
@section('page_title', 'داشبورد')

@section('content')

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- آمار کلی --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

        {{-- کل کاربران سایت --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">کاربران سایت</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format(\App\Models\User::count()) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- آهنگ‌ها --}}
        <a href="{{ route('admin.songs.index') }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:border-purple-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">آهنگ‌ها</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format(\App\Models\Song::count()) }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                    </svg>
                </div>
            </div>
        </a>

        {{-- درخواست‌های در انتظار --}}
        <a href="{{ route('admin.song-requests.index', ['status' => 'pending']) }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:border-yellow-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">درخواست‌های در انتظار</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ number_format(\App\Models\SongRequest::pending()->count()) }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-yellow-50 text-yellow-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </a>

        {{-- دانلود کل --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">دانلود کل</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ number_format(\App\Models\Song::sum('downloads_count')) }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- آمار اعضای ربات‌ها --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

        {{-- اعضای تلگرام --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">اعضای ربات تلگرام</p>
                    <p class="text-2xl font-bold text-sky-600 mt-1">
                        @if(class_exists(\App\Models\BotUser::class))
                            {{ number_format(\App\Models\BotUser::telegram()->count()) }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="w-12 h-12 bg-sky-50 text-sky-600 rounded-xl flex items-center justify-center text-2xl">✈️</div>
            </div>
        </div>

        {{-- اعضای بله --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">اعضای ربات بله</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">
                        @if(class_exists(\App\Models\BotUser::class))
                            {{ number_format(\App\Models\BotUser::bale()->count()) }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-2xl">🤖
                </div>
            </div>
        </div>

        {{-- کاربران بلاک‌شده --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">کاربران بلاک‌شده</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">
                        @if(class_exists(\App\Models\BotUser::class))
                            {{ number_format(\App\Models\BotUser::blocked()->count()) }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="w-12 h-12 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-2xl">🚫</div>
            </div>
        </div>

        {{-- خطاهای امروز --}}
        <a href="{{ route('admin.activity-logs.index', ['level' => 'error']) }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:border-orange-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">اعلانات امروز</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        @if(class_exists(\App\Models\ActivityLog::class))
                            {{ number_format(\App\Models\ActivityLog::today()->count()) }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center text-2xl">🔔
                </div>
            </div>
        </a>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- لیست‌های آخرین فعالیت‌ها --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- آخرین درخواست‌ها --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">آخرین درخواست‌ها</h2>
                <a href="{{ route('admin.song-requests.index') }}"
                    class="text-sm text-indigo-600 hover:text-indigo-700">مشاهده همه</a>
            </div>
            <div class="space-y-3">
                @foreach(\App\Models\SongRequest::latest()->take(5)->get() as $req)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $req->query }}</p>
                            <p class="text-xs text-gray-500">{{ $req->full_name }}</p>
                        </div>
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full
                                                                    {{ $req->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                                    {{ $req->status === 'processing' ? 'bg-blue-100 text-blue-800' : '' }}
                                                                    {{ $req->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                                                    {{ $req->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                                                ">{{ $req->status_label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- آخرین آهنگ‌ها --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">آخرین آهنگ‌ها</h2>
                <a href="{{ route('admin.songs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700">مشاهده
                    همه</a>
            </div>
            <div class="space-y-3">
                @foreach(\App\Models\Song::latest()->take(5)->get() as $song)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $song->title }}</p>
                            <p class="text-xs text-gray-500">{{ $song->artist_name }}</p>
                        </div>
                        <span class="text-xs text-gray-500 whitespace-nowrap">{{ number_format($song->downloads_count) }}
                            دانلود</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

@endsection