@extends('layouts.admin') {{-- در صورتی که نام قالب اصلی شما متفاوت است (مثلا layouts.app یا layouts.master) این قسمت را
تغییر دهید --}}

@section('title', 'مدیریت کاربران ربات')

@push('styles')
    <style>
        /* انیمیشن‌های ظریف برای پیام‌های سیستم */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.2s ease-out forwards;
        }
    </style>
@endpush

@section('page_title', 'مدیریت کاربران ربات (تلگرام و بله)')

@section('header_actions')
    <div class="flex items-center gap-3">
        {{-- دکمه خروجی اکسل / CSV --}}
        <a href="{{ route('admin.bot-users.export', request()->query()) }}"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 transition-all">
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                </path>
            </svg>
            <span>خروجی CSV</span>
        </a>

        {{-- دکمه به‌روزرسانی صفحه --}}
        <a href="{{ route('admin.bot-users.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                </path>
            </svg>
            <span>بروزرسانی</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="space-y-6 text-right" dir="rtl">

        {{-- نمایش پیام‌های موفقیت یا خطا (Alerts) --}}
        @if(session('success'))
            <div
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-900/30 dark:border-emerald-800 dark:text-emerald-300 flex items-center justify-between animate-fade-in shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-800/50 rounded-lg text-emerald-600 dark:text-emerald-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <p class="font-medium text-sm">{{ session('success') }}</p>
                </div>
                <button type="button" onclick="this.parentElement.remove()"
                    class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        @endif

        {{-- ۱. کارت‌های آمار سریع (Header Stats Cards) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            {{-- کل کاربران --}}
            <div
                class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between transition hover:shadow-md">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">کل کاربران ربات</p>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white">{{ number_format($stats['total']) }}
                    </h3>
                </div>
                <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl text-indigo-600 dark:text-indigo-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                </div>
            </div>

            {{-- کاربران تلگرام --}}
            <div
                class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between transition hover:shadow-md">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">کاربران تلگرام</p>
                    <h3 class="mt-2 text-2xl font-bold text-sky-600 dark:text-sky-400">
                        {{ number_format($stats['telegram']) }}
                    </h3>
                </div>
                <div class="p-3 bg-sky-50 dark:bg-sky-900/30 rounded-xl text-sky-500">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                    </svg>
                </div>
            </div>

            {{-- کاربران بله --}}
            <div
                class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between transition hover:shadow-md">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">کاربران بله</p>
                    <h3 class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ number_format($stats['bale']) }}
                    </h3>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl text-emerald-600 dark:text-emerald-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                        </path>
                    </svg>
                </div>
            </div>

            {{-- کاربران مسدود --}}
            <div
                class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between transition hover:shadow-md">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">کاربران مسدود شده</p>
                    <h3 class="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                        {{ number_format($stats['blocked']) }}
                    </h3>
                </div>
                <div class="p-3 bg-rose-50 dark:bg-rose-900/30 rounded-xl text-rose-600 dark:text-rose-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                        </path>
                    </svg>
                </div>
            </div>

            {{-- فعال در ۲۴ ساعت گذشته --}}
            <div
                class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between transition hover:shadow-md">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">فعال در ۲۴ ساعت اخیر</p>
                    <h3 class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {{ number_format($stats['active_24h']) }}
                    </h3>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-900/30 rounded-xl text-amber-600 dark:text-amber-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- ۲. بخش جستجو و فیلتر پیشرفته (Advanced Search & Filter Form) --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-6">
            <form action="{{ route('admin.bot-users.index') }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                    {{-- جستجوی کلمه کلیدی --}}
                    <div class="lg:col-span-1">
                        <label for="search" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            جستجوی کاربر (نام، شناسه، نام کاربری)
                        </label>
                        <div class="relative rounded-xl shadow-sm">
                            <div
                                class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text" name="search" id="search" value="{{ $search }}"
                                placeholder="مثلاً: 123456 یا ali@"
                                class="block w-full pr-9 pl-3 py-2 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                    </div>

                    {{-- فیلتر پلتفرم --}}
                    <div>
                        <label for="platform" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            پلتفرم ربات
                        </label>
                        <select name="platform" id="platform"
                            class="block w-full py-2 px-3 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition">
                            <option value="">همه پلتفرم‌ها (تلگرام و بله)</option>
                            <option value="{{ \App\Models\BotUser::PLATFORM_TELEGRAM }}" {{ $platform === \App\Models\BotUser::PLATFORM_TELEGRAM ? 'selected' : '' }}>تلگرام (Telegram)
                            </option>
                            <option value="{{ \App\Models\BotUser::PLATFORM_BALE }}" {{ $platform === \App\Models\BotUser::PLATFORM_BALE ? 'selected' : '' }}>بله (Bale)</option>
                        </select>
                    </div>

                    {{-- فیلتر وضعیت بلاک --}}
                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            وضعیت دسترسی
                        </label>
                        <select name="status" id="status"
                            class="block w-full py-2 px-3 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>فعال (عادی)</option>
                            <option value="blocked" {{ $status === 'blocked' ? 'selected' : '' }}>مسدود شده (Blocked)</option>
                        </select>
                    </div>

                    {{-- مرتب‌سازی --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="sort_by" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                مرتب‌سازی بر اساس
                            </label>
                            <select name="sort_by" id="sort_by"
                                class="block w-full py-2 px-2 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition">
                                <option value="last_activity_at" {{ $sortBy === 'last_activity_at' ? 'selected' : '' }}>آخرین
                                    فعالیت</option>
                                <option value="created_at" {{ $sortBy === 'created_at' ? 'selected' : '' }}>تاریخ عضویت
                                </option>
                                <option value="failed_send_count" {{ $sortBy === 'failed_send_count' ? 'selected' : '' }}>
                                    خطاهای ارسال</option>
                                <option value="user_id" {{ $sortBy === 'user_id' ? 'selected' : '' }}>شناسه پلتفرم</option>
                            </select>
                        </div>
                        <div>
                            <label for="sort_direction"
                                class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                ترتیب
                            </label>
                            <select name="sort_direction" id="sort_direction"
                                class="block w-full py-2 px-2 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition">
                                <option value="desc" {{ $sortDirection === 'desc' ? 'selected' : '' }}>نزولی (جدیدترین)
                                </option>
                                <option value="asc" {{ $sortDirection === 'asc' ? 'selected' : '' }}>صعودی (قدیمی‌ترین)
                                </option>
                            </select>
                        </div>
                    </div>

                </div>

                {{-- دکمه‌های عملیات فیلتر --}}
                <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <label for="per_page" class="text-xs text-gray-500 dark:text-gray-400">تعداد در صفحه:</label>
                        <select name="per_page" id="per_page" onchange="this.form.submit()"
                            class="py-1 px-2 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="15" {{ $perPage == 15 ? 'selected' : '' }}>15</option>
                            <option value="30" {{ $perPage == 30 ? 'selected' : '' }}>30</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3">
                        @if($search || $platform || $status || $sortBy !== 'last_activity_at' || $sortDirection !== 'desc')
                            <a href="{{ route('admin.bot-users.index') }}"
                                class="text-xs text-rose-600 hover:text-rose-700 dark:text-rose-400 font-medium flex items-center gap-1 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                <span>حذف تمام فیلترها</span>
                            </a>
                        @endif

                        <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <span>اعمال فیلتر و جستجو</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- ۳. جدول اطلاعات کاربران (Users Table) --}}
        <div
            class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right border-collapse">
                    <thead>
                        <tr
                            class="bg-gray-50/75 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold">
                            <th scope="col" class="py-4 px-4">کاربر</th>
                            <th scope="col" class="py-4 px-4 text-center">پلتفرم ربات</th>
                            <th scope="col" class="py-4 px-4">شناسه (user_id)</th>
                            <th scope="col" class="py-4 px-4 text-center">وضعیت</th>
                            <th scope="col" class="py-4 px-4 text-center">خطای ارسال</th>
                            <th scope="col" class="py-4 px-4">آخرین فعالیت</th>
                            <th scope="col" class="py-4 px-4">تاریخ عضویت</th>
                            <th scope="col" class="py-4 px-4 text-left">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($users as $user)
                            <tr
                                class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors {{ $user->is_blocked ? 'bg-rose-50/30 dark:bg-rose-950/10' : '' }}">

                                {{-- کاربر (نام، آواتار، نام کاربری) --}}
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shadow-inner
                                                                                                                {{ $user->platform === \App\Models\BotUser::PLATFORM_TELEGRAM ? 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300' }}">
                                            {{ mb_substr($user->first_name ?? $user->platform_label, 0, 1, 'UTF-8') }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
                                                <span>{{ $user->full_name }}</span>
                                            </div>
                                            @if($user->username)
                                                <a href="{{ $user->profile_url }}" target="_blank"
                                                    class="text-xs text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 block dir-ltr text-right transition">
                                                    {{ '@@' . ltrim($user->username, '@') }}
                                                </a>
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-gray-500 italic">بدون نام کاربری</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- پلتفرم (تفکیک بله و تلگرام با بج‌های اختصاصی) --}}
                                <td class="py-3.5 px-4 text-center">
                                    @if($user->platform === \App\Models\BotUser::PLATFORM_TELEGRAM)
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-sky-50 text-sky-700 border border-sky-200/60 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-800">
                                            <svg class="w-3.5 h-3.5 text-sky-500 fill-current" viewBox="0 0 24 24">
                                                <path
                                                    d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                                            </svg>
                                            <span>{{ $user->platform_label }}</span>
                                        </span>
                                    @elseif($user->platform === \App\Models\BotUser::PLATFORM_BALE)
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/60 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800">
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                                </path>
                                            </svg>
                                            <span>{{ $user->platform_label }}</span>
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            {{ $user->platform_label }}
                                        </span>
                                    @endif
                                </td>

                                {{-- شناسه پلتفرم (user_id) --}}
                                <td class="py-3.5 px-4 font-mono text-xs text-gray-700 dark:text-gray-300">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $user->user_id }}</span>
                                        <button type="button" onclick="copyToClipboard('{{ $user->user_id }}', this)"
                                            title="کپی شناسه"
                                            class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition focus:outline-none">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>

                                {{-- وضعیت بلاک --}}
                                <td class="py-3.5 px-4 text-center">
                                    @if($user->is_blocked)
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300"
                                            title="تاریخ مسدودی: {{ $user->blocked_at ? $user->blocked_at->format('Y/m/d H:i') : 'نامشخص' }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                            <span>مسدود شده</span>
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>فعال</span>
                                        </span>
                                    @endif
                                </td>

                                {{-- تعداد خطای ارسال (failed_send_count) --}}
                                <td class="py-3.5 px-4 text-center">
                                    @if($user->failed_send_count > 0)
                                        <div
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800">
                                            <span class="font-bold text-xs">{{ number_format($user->failed_send_count) }}</span>
                                            <form action="{{ route('admin.bot-users.reset-failed', $user->id) }}" method="POST"
                                                class="inline"
                                                onsubmit="return confirm('آیا از صفر کردن خطاهای این کاربر اطمینان دارید؟');">
                                                @csrf
                                                <button type="submit" title="صفر کردن خطاها"
                                                    class="text-amber-600 hover:text-amber-900 dark:hover:text-amber-100">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 text-xs">۰</span>
                                    @endif
                                </td>

                                {{-- آخرین فعالیت (last_activity_at) --}}
                                <td class="py-3.5 px-4 text-gray-600 dark:text-gray-300 text-xs">
                                    @if($user->last_activity_at)
                                        <span
                                            class="text-[10px] text-gray-400 dir-ltr inline-block">{{ jdate($user->last_activity_at)->ago() }}</span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 italic">نامشخص</span>
                                    @endif
                                </td>

                                {{-- تاریخ عضویت (created_at) --}}
                                <td class="py-3.5 px-4 text-gray-500 dark:text-gray-400 text-xs dir-ltr text-right">
                                    {{  jdate($user->created_at)->ago() }}
                                </td>

                                {{-- ستون عملیات (مشاهده، مسدود/رفع مسدودیت) --}}
                                <td class="py-3.5 px-4 text-left">
                                    <div class="flex items-center justify-end gap-2">

                                        {{-- دکمه مشاهده جزئیات --}}
                                        <a href="{{ route('admin.bot-users.show', $user->id) }}" title="مشاهده جزئیات کامل"
                                            class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-gray-700 dark:hover:text-indigo-400 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                        </a>

                                        {{-- دکمه بلاک / آنبلاک --}}
                                        <form action="{{ route('admin.bot-users.toggle-block', $user->id) }}" method="POST"
                                            class="inline"
                                            onsubmit="return confirm('{{ $user->is_blocked ? 'آیا از رفع مسدودیت این کاربر اطمینان دارید؟' : 'آیا از مسدود کردن این کاربر اطمینان دارید؟ پیام‌های ربات برای این کاربر ارسال نخواهد شد.' }}');">
                                            @csrf
                                            @if($user->is_blocked)
                                                <button type="submit" title="خارج کردن از لیست مسدودها (آنبلاک)"
                                                    class="p-1.5 text-rose-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-gray-700 dark:hover:text-emerald-400 rounded-lg transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2z">
                                                        </path>
                                                    </svg>
                                                </button>
                                            @else
                                                <button type="submit" title="مسدود کردن کاربر (بلاک)"
                                                    class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-gray-700 dark:hover:text-rose-400 rounded-lg transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                                        </path>
                                                    </svg>
                                                </button>
                                            @endif
                                        </form>

                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center">
                                    <div class="max-w-sm mx-auto text-center space-y-3">
                                        <div
                                            class="w-16 h-16 mx-auto bg-gray-100 dark:bg-gray-700/50 rounded-full flex items-center justify-center text-gray-400 dark:text-gray-500">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                                </path>
                                            </svg>
                                        </div>
                                        <h4 class="font-bold text-gray-800 dark:text-gray-200">کاربری یافت نشد!</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            هیچ کاربر رباتی با پارامترهای جستجو و فیلتر فعلی مطابقت ندارد.
                                        </p>
                                        @if($search || $platform || $status)
                                            <a href="{{ route('admin.bot-users.index') }}"
                                                class="inline-block mt-2 px-4 py-2 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition dark:bg-indigo-900/30 dark:text-indigo-400">
                                                پاک کردن فیلترها و نمایش همه
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ۴. صفحه‌بندی (Pagination) --}}
            @if($users->hasPages() || $users->total() > 0)
                <div
                    class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        نمایش <span class="font-semibold text-gray-800 dark:text-white">{{ $users->firstItem() ?? 0 }}</span> تا
                        <span class="font-semibold text-gray-800 dark:text-white">{{ $users->lastItem() ?? 0 }}</span> از
                        <span class="font-semibold text-gray-800 dark:text-white">{{ number_format($users->total()) }}</span>
                        کاربر
                    </div>
                    <div>
                        {{ $users->links() }}
                    </div>
                </div>
            @endif
        </div>

    </div>
@endsection

@stack('scripts')
@push('scripts')
    <script>
        /**
         * کپی کردن شناسه پلتفرم در کلیپ‌بورد با فیدبک بصری به کاربر
         */
        function copyToClipboard(text, buttonElement) {
            navigator.clipboard.writeText(text).then(function () {
                const originalHTML = buttonElement.innerHTML;
                buttonElement.innerHTML = `
                                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            `;
                setTimeout(() => {
                    buttonElement.innerHTML = originalHTML;
                }, 1500);
            }).catch(function (err) {
                console.error('خطا در کپی متن: ', err);
            });
        }
    </script>
@endpush