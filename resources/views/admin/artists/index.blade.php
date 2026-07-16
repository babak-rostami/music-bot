@extends('layouts.admin')

@section('title', 'مدیریت خوانندگان')

@push('styles')
    <style>
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

@section('page_title', 'مدیریت و لیست خوانندگان (Artists)')

@section('header_actions')
    <div class="flex items-center gap-3">
        {{-- دکمه مشاهده تمام آهنگ‌ها --}}
        <a href="{{ route('admin.songs.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 transition">
            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3">
                </path>
            </svg>
            <span>مشاهده همه آهنگ‌ها</span>
        </a>

        {{-- دکمه افزودن خواننده جدید --}}
        <a href="{{ route('admin.artists.create') }}"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-emerald-600 border border-transparent rounded-lg shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
            </svg>
            <span>افزودن خواننده جدید</span>
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
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- کل خوانندگان --}}
            <div
                class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between transition hover:shadow-md">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">کل خوانندگان ثبت‌شده</p>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white">{{ number_format($stats['total']) }}
                    </h3>
                </div>
                <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl text-indigo-600 dark:text-indigo-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
            </div>

            {{-- خوانندگان دارای آهنگ --}}
            <div
                class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between transition hover:shadow-md">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">خوانندگان دارای آهنگ</p>
                    <h3 class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ number_format($stats['with_songs']) }}</h3>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl text-emerald-600 dark:text-emerald-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3">
                        </path>
                    </svg>
                </div>
            </div>

            {{-- نیازمند تکمیل بیوگرافی --}}
            <div
                class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between transition hover:shadow-md">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">بدون بیوگرافی (نیازمند تکمیل)</p>
                    <h3 class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {{ number_format($stats['without_bio']) }}</h3>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-900/30 rounded-xl text-amber-600 dark:text-amber-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                        </path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- ۲. بخش جستجو و فیلتر --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-6">
            <form action="{{ route('admin.artists.index') }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    {{-- جستجوی کلمه کلیدی --}}
                    <div class="md:col-span-1">
                        <label for="search" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            جستجوی خواننده (نام، اسلاگ، بیوگرافی)
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
                                placeholder="مثلاً: محسن چاوشی یا mohsen-chavoshi"
                                class="block w-full pr-9 pl-3 py-2 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                    </div>

                    {{-- مرتب‌سازی --}}
                    <div>
                        <label for="sort_by" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            مرتب‌سازی بر اساس
                        </label>
                        <select name="sort_by" id="sort_by"
                            class="block w-full py-2 px-3 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition">
                            <option value="songs_count" {{ $sortBy === 'songs_count' ? 'selected' : '' }}>تعداد آهنگ‌ها
                                (بیشترین به کمترین)</option>
                            <option value="name" {{ $sortBy === 'name' ? 'selected' : '' }}>نام خواننده (الفبایی)</option>
                            <option value="created_at" {{ $sortBy === 'created_at' ? 'selected' : '' }}>تاریخ ثبت در سیستم
                            </option>
                            <option value="id" {{ $sortBy === 'id' ? 'selected' : '' }}>شناسه (ID)</option>
                        </select>
                    </div>

                    {{-- ترتیب مرتب‌سازی --}}
                    <div>
                        <label for="sort_direction"
                            class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            ترتیب نمایش
                        </label>
                        <select name="sort_direction" id="sort_direction"
                            class="block w-full py-2 px-3 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition">
                            <option value="desc" {{ $sortDirection === 'desc' ? 'selected' : '' }}>نزولی (بیشترین / جدیدترین)
                            </option>
                            <option value="asc" {{ $sortDirection === 'asc' ? 'selected' : '' }}>صعودی (کمترین / قدیمی‌ترین)
                            </option>
                        </select>
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
                        @if($search || $sortBy !== 'songs_count' || $sortDirection !== 'desc')
                            <a href="{{ route('admin.artists.index') }}"
                                class="text-xs text-rose-600 hover:text-rose-700 dark:text-rose-400 font-medium flex items-center gap-1 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                <span>حذف جستجو و فیلترها</span>
                            </a>
                        @endif

                        <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <span>اعمال جستجو و مرتب‌سازی</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- ۳. جدول اطلاعات خوانندگان (Artists Table) --}}
        <div
            class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right border-collapse">
                    <thead>
                        <tr
                            class="bg-gray-50/75 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold">
                            <th scope="col" class="py-4 px-4">خواننده و بیوگرافی</th>
                            <th scope="col" class="py-4 px-4 text-center">اسلاگ (Slug - یکتا)</th>
                            <th scope="col" class="py-4 px-4 text-center">تعداد آهنگ‌ها و فیلتر</th>
                            <th scope="col" class="py-4 px-4">تاریخ ثبت</th>
                            <th scope="col" class="py-4 px-4 text-left">عملیات (ویرایش / حذف)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($artists as $artist)
                                        <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors">

                                            {{-- نام خواننده و خلاصه بیوگرافی --}}
                                            <td class="py-4 px-4">
                                                <div class="flex items-start gap-3">
                                                    <div
                                                        class="flex-shrink-0 w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center font-bold text-base shadow-sm mt-0.5">
                                                        {{ mb_substr($artist->name, 0, 1, 'UTF-8') }}
                                                    </div>
                                                    <div class="max-w-md">
                                                        <div
                                                            class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
                                                            <span>{{ $artist->name }}</span>
                                                            <span class="text-xs font-mono text-gray-400">#{{ $artist->id }}</span>
                                                        </div>
                                                        @if($artist->bio)
                                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2 leading-relaxed"
                                                                title="{{ $artist->bio }}">
                                                                {{ Str::limit($artist->bio, 110, '...') }}
                                                            </p>
                                                        @else
                                                            <span
                                                                class="text-xs text-amber-600/80 dark:text-amber-400/80 italic mt-1 block">بدون
                                                                بیوگرافی</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>

                                            {{-- اسلاگ (Slug) --}}
                                            <td class="py-4 px-4 text-center">
                                                <span
                                                    class="inline-block px-3 py-1 font-mono text-xs rounded-lg bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dir-ltr text-center">
                                                    {{ $artist->slug }}
                                                </span>
                                            </td>

                                            {{-- دکمه آهنگ‌ها همراه با فیلتر مخصوص خواننده --}}
                                            <td class="py-4 px-4 text-center">
                                                <a href="{{ route('admin.songs.index', ['artist_id' => $artist->id]) }}"
                                                    title="مشاهده و فیلتر تمام آهنگ‌های {{ $artist->name }}"
                                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold shadow-sm transition-all
                                                              {{ $artist->songs_count > 0
                            ? 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100 hover:shadow border border-indigo-200/60 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800'
                            : 'bg-gray-50 text-gray-500 hover:bg-gray-100 border border-gray-200 dark:bg-gray-700/50 dark:text-gray-400 dark:border-gray-600' }}">
                                                    <svg class="w-4 h-4 text-indigo-500 dark:text-indigo-400 flex-shrink-0" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3">
                                                        </path>
                                                    </svg>
                                                    <span>آهنگ‌ها (<strong>{{ number_format($artist->songs_count) }}</strong>)</span>
                                                    <svg class="w-3 h-3 text-indigo-400 dir-ltr" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                            d="M15 19l-7-7 7-7"></path>
                                                    </svg>
                                                </a>
                                            </td>

                                            {{-- تاریخ ثبت --}}
                                            <td class="py-4 px-4 text-gray-500 dark:text-gray-400 text-xs dir-ltr text-right font-mono">
                                                {{ jdate($artist->created_at)->ago() }}
                                            </td>

                                            {{-- ستون عملیات (ویرایش و حذف) --}}
                                            <td class="py-4 px-4 text-left">
                                                <div class="flex items-center justify-end gap-2">

                                                    {{-- دکمه ویرایش (نام و بیوگرافی) --}}
                                                    <a href="{{ route('admin.artists.edit', $artist->id) }}"
                                                        title="ویرایش نام و بیوگرافی خواننده"
                                                        class="p-2 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/60 transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                            </path>
                                                        </svg>
                                                    </a>

                                                    {{-- دکمه حذف خواننده --}}
                                                    {{-- <form action="{{ route('admin.artists.destroy', $artist->id) }}" method="POST"
                                                        class="inline"
                                                        onsubmit="return confirm('آیا از حذف خواننده «{{ $artist->name }}» اطمینان دارید؟ در صورت حذف، ارتباط او با آهنگ‌ها از جدول واسط پاک خواهد شد.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="حذف خواننده"
                                                            class="p-2 text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg dark:bg-rose-900/30 dark:text-rose-300 dark:hover:bg-rose-900/60 transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                </path>
                                                            </svg>
                                                        </button>
                                                    </form> --}}

                                                </div>
                                            </td>

                                        </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center">
                                    <div class="max-w-sm mx-auto text-center space-y-3">
                                        <div
                                            class="w-16 h-16 mx-auto bg-gray-100 dark:bg-gray-700/50 rounded-full flex items-center justify-center text-gray-400 dark:text-gray-500">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                                </path>
                                            </svg>
                                        </div>
                                        <h4 class="font-bold text-gray-800 dark:text-gray-200">خواننده‌ای یافت نشد!</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            هیچ خواننده‌ای با جستجو و فیلتر فعلی مطابقت ندارد یا هنوز خواننده‌ای ثبت نشده است.
                                        </p>
                                        @if($search || $sortBy !== 'songs_count')
                                            <a href="{{ route('admin.artists.index') }}"
                                                class="inline-block mt-2 px-4 py-2 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition dark:bg-indigo-900/30 dark:text-indigo-400">
                                                پاک کردن جستجو و نمایش همه
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
            @if($artists->hasPages() || $artists->total() > 0)
                <div
                    class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        نمایش <span class="font-semibold text-gray-800 dark:text-white">{{ $artists->firstItem() ?? 0 }}</span>
                        تا
                        <span class="font-semibold text-gray-800 dark:text-white">{{ $artists->lastItem() ?? 0 }}</span> از
                        <span class="font-semibold text-gray-800 dark:text-white">{{ number_format($artists->total()) }}</span>
                        خواننده
                    </div>
                    <div>
                        {{ $artists->links() }}
                    </div>
                </div>
            @endif
        </div>

    </div>
@endsection