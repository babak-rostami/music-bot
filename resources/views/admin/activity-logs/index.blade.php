@extends('layouts.admin')

@section('title', 'اعلانات')
@section('page_title', 'اعلانات و رویدادها')

@section('content')

    @if (session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- کارت‌های آمار کلی --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        {{-- کل اعلانات --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">کل اعلانات</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total']) }}</p>
                    <p class="text-xs text-blue-600 mt-1">{{ $stats['today'] }} امروز</p>
                </div>
                <div class="w-11 h-11 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl">📋</div>
            </div>
        </div>

        {{-- خطاها --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">خطاها</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($stats['errors']) }}</p>
                    <p class="text-xs text-red-500 mt-1">{{ $stats['errors_today'] }} امروز</p>
                </div>
                <div class="w-11 h-11 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-xl">❗</div>
            </div>
        </div>

        {{-- تلگرام --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">تلگرام</p>
                    <p class="text-2xl font-bold text-sky-600 mt-1">{{ number_format($stats['telegram']) }}</p>
                </div>
                <div class="w-11 h-11 bg-sky-50 text-sky-600 rounded-xl flex items-center justify-center text-xl">✈️</div>
            </div>
        </div>

        {{-- بله --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">بله</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($stats['bale']) }}</p>
                </div>
                <div class="w-11 h-11 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl">🤖
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- خلاصه بر اساس نوع رویداد --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">📊 تفکیک بر اساس نوع رویداد</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach ($stats['by_type'] as $typeKey => $info)
                <a href="{{ route('admin.activity-logs.index', ['type' => $typeKey]) }}"
                    class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/40 transition">
                    <span class="text-xl">{{ $info['icon'] }}</span>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 truncate">{{ $info['label'] }}</p>
                        <p class="text-sm font-bold text-gray-900">
                            {{ number_format($info['total']) }}
                            @if ($info['today'] > 0)
                                <span class="text-xs font-normal text-green-600">+{{ $info['today'] }} امروز</span>
                            @endif
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- فرم فیلتر + جستجو --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="space-y-4">

            {{-- جستجو --}}
            <div class="relative">
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="جستجو در پیام، شناسه کاربر یا chat_id..."
                    class="w-full pr-10 pl-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <svg class="absolute right-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            {{-- فیلترهای کشویی --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

                {{-- نوع --}}
                <select name="type"
                    class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">همه انواع</option>
                    @foreach ($typeOptions as $key => $label)
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                {{-- پلتفرم --}}
                <select name="platform"
                    class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">همه پلتفرم‌ها</option>
                    @foreach ($platformOptions as $key => $label)
                        <option value="{{ $key }}" @selected(request('platform') === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                {{-- سطح --}}
                <select name="level"
                    class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">همه سطوح</option>
                    @foreach ($levelOptions as $key => $label)
                        <option value="{{ $key }}" @selected(request('level') === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                {{-- از تاریخ --}}
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                    class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">

                {{-- تا تاریخ --}}
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                    class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition">
                    🔍 اعمال فیلتر
                </button>
                <a href="{{ route('admin.activity-logs.index') }}"
                    class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition">
                    پاک کردن
                </a>

                {{-- دکمه پاکسازی لاگ‌های قدیمی --}}
                <div class="mr-auto relative" data-dropdown>
                    <button type="button" onclick="toggleDropdown('clearMenu')"
                        class="px-4 py-2.5 bg-red-50 text-red-600 text-sm font-medium rounded-xl hover:bg-red-100 transition">
                        🗑 پاکسازی
                        <svg class="inline w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="clearMenu"
                        class="hidden absolute left-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-lg z-10 p-3 space-y-2"
                        data-dropdown-menu>
                        <p class="text-xs text-gray-500 px-2">⚠️ پاکسازی دائمی است:</p>
                        <form method="POST" action="{{ route('admin.activity-logs.clear') }}">
                            @csrf
                            <input type="hidden" name="days" value="30">
                            <button type="submit"
                                class="w-full text-right px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                                قدیمی‌تر از ۳۰ روز
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.activity-logs.clear') }}">
                            @csrf
                            <input type="hidden" name="days" value="7">
                            <button type="submit"
                                class="w-full text-right px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                                قدیمی‌تر از ۷ روز
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.activity-logs.clear') }}"
                            onsubmit="return confirm('آیا مطمئن هستید؟ همه اعلانات پاک می‌شوند!')">
                            @csrf
                            <input type="hidden" name="days" value="0">
                            <button type="submit"
                                class="w-full text-right px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
                                پاک کردن همه ❌
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- لیست اعلانات --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

        @if ($logs->isEmpty())
            <div class="p-12 text-center">
                <div class="text-5xl mb-3">📭</div>
                <p class="text-gray-500 text-sm">هیچ اعلانی یافت نشد.</p>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($logs as $log)
                    <div class="p-4 hover:bg-gray-50/70 transition flex items-start gap-4 border-r-4 {{ $log->level_border }}">

                        {{-- آیکون --}}
                        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-lg">
                            {{ $log->type_icon }}
                        </div>

                        {{-- محتوا --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="text-sm font-semibold text-gray-900">{{ $log->type_label }}</span>
                                <span
                                    class="text-xs font-medium px-2 py-0.5 rounded-full {{ $log->level_badge }}">{{ $log->level_label }}</span>
                                @if ($log->platform)
                                    <span
                                        class="text-xs font-medium px-2 py-0.5 rounded-full {{ $log->platform_badge }}">{{ $log->platform_label }}</span>
                                @endif
                            </div>

                            <p class="text-sm text-gray-600 line-clamp-2">{{ $log->message }}</p>

                            <div class="flex items-center gap-4 mt-1.5 text-xs text-gray-400">
                                @if ($log->user_id)
                                    <span>👤 {{ $log->user_id }}</span>
                                @endif
                                @if ($log->chat_id)
                                    <span>💬 {{ $log->chat_id }}</span>
                                @endif
                                <span>🕐 {{ jdate($log->created_at)->ago() }}</span>
                            </div>
                        </div>

                        {{-- اکشن‌ها --}}
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <a href="{{ route('admin.activity-logs.show', $log) }}"
                                class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
                                title="جزئیات">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('admin.activity-logs.destroy', $log) }}"
                                onsubmit="return confirm('حذف شود؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="حذف">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- پیجینیشن --}}
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

@endsection