@extends('layouts.admin')

@section('title', 'ویرایش خواننده: ' . $artist->name)

@section('page_title', 'ویرایش اطلاعات خواننده (Edit Artist)')

@section('header_actions')
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.artists.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                </path>
            </svg>
            <span>بازگشت به لیست خوانندگان</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="max-w-2xl mx-auto space-y-6 text-right" dir="rtl">

        {{-- نمایش پیام‌های خطا (Validation Errors) --}}
        @if($errors->any())
            <div
                class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-900/30 dark:border-rose-800 dark:text-rose-300 shadow-sm space-y-2">
                <div class="flex items-center gap-2 font-bold text-sm">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>لطفاً خطاهای زیر را بررسی و رفع کنید:</span>
                </div>
                <ul class="list-disc list-inside text-xs space-y-1 pr-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-6 sm:p-8">

            <div class="pb-4 border-b border-gray-100 dark:border-gray-700 mb-6 flex items-center justify-between">
                <h4 class="font-bold text-gray-800 dark:text-white text-base flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                        </path>
                    </svg>
                    <span>ویرایش اطلاعات: {{ $artist->name }}</span>
                </h4>
                <span
                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800">
                    شناسه: #{{ $artist->id }}
                </span>
            </div>

            <form action="{{ route('admin.artists.update', $artist->id) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- ۱. نام خواننده --}}
                <div>
                    <label for="name" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        نام خواننده <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name', $artist->name) }}" required
                        placeholder="مثلاً: محسن چاوشی"
                        class="block w-full py-3 px-4 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition shadow-sm">
                </div>

                {{-- ۲. اسلاگ (Slug - غیر قابل ویرایش طبق خواسته کاربر) --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="slug" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            اسلاگ (Slug - آدرس یکتای URL)
                        </label>
                        <span
                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                </path>
                            </svg>
                            <span>ثابت و غیرقابل تغییر</span>
                        </span>
                    </div>
                    <input type="text" value="{{ $artist->slug }}" disabled readonly
                        class="block w-full py-3 px-4 text-sm font-mono bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-xl cursor-not-allowed dir-ltr text-left shadow-inner">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span>
                        <span>طبق تنظیمات سیستم، اسلاگ جهت سئو و جلوگیری از خرابی لینک‌های آهنگ‌های این خواننده ثابت
                            است.</span>
                    </p>
                </div>

                {{-- ۳. بیوگرافی (Bio) --}}
                <div>
                    <label for="bio" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        بیوگرافی و توضیحات خواننده
                    </label>
                    <textarea name="bio" id="bio" rows="6" placeholder="توضیحات و بیوگرافی خواننده، سبک، فعالیت‌ها و..."
                        class="block w-full py-3 px-4 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition shadow-sm">{{ old('bio', $artist->bio) }}</textarea>
                </div>

                {{-- دکمه‌های فرم --}}
                <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <a href="{{ route('admin.songs.index', ['artist_id' => $artist->id]) }}"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3">
                            </path>
                        </svg>
                        <span>مشاهده تمام آهنگ‌های این خواننده ({{ number_format($artist->songs()->count()) }})</span>
                    </a>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.artists.index') }}"
                            class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 transition">
                            انصراف
                        </a>
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                            <span>ذخیره تغییرات</span>
                        </button>
                    </div>
                </div>

            </form>

        </div>

    </div>
@endsection