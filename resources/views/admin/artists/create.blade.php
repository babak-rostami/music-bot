@extends('layouts.admin')

@section('title', 'افزودن خواننده جدید')

@section('page_title', 'ثبت و افزودن خواننده جدید (New Artist)')

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
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span>اطلاعات خواننده جدید</span>
                </h4>
                <span
                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800">
                    فرم ثبت جدید
                </span>
            </div>

            <form action="{{ route('admin.artists.store') }}" method="POST" class="space-y-6">
                @csrf

                {{-- ۱. نام خواننده --}}
                <div>
                    <label for="name" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        نام خواننده <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        placeholder="مثلاً: محسن چاوشی (Mohsen Chavoshi)"
                        class="block w-full py-3 px-4 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">نام خواننده باید یکتا باشد و در سیستم تکراری
                        نباشد.</p>
                </div>

                {{-- ۲. اسلاگ (Slug) --}}
                <div>
                    <label for="slug" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        اسلاگ (Slug - آدرس یکتا برای URL) <span class="text-xs font-normal text-gray-400">(اختیاری)</span>
                    </label>
                    <input type="text" name="slug" id="slug" value="{{ old('slug') }}"
                        placeholder="مثلاً: mohsen-chavoshi (اگر خالی بگذارید به صورت خودکار از روی نام ساخته می‌شود)"
                        class="block w-full py-3 px-4 text-sm font-mono border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm dir-ltr text-left">
                    <div
                        class="mt-2 p-2.5 bg-gray-50 dark:bg-gray-700/40 rounded-xl border border-gray-100 dark:border-gray-700 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                        <svg class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><strong>نکته مهم:</strong> پس از ثبت، اسلاگ برای ثبات آدرس‌های سایت تغییر نمی‌کند؛ اگر خالی
                            بگذارید سیستم بهترین اسلاگ را می‌سازد.</span>
                    </div>
                </div>

                {{-- ۳. بیوگرافی (Bio) --}}
                <div>
                    <label for="bio" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        بیوگرافی و توضیحات خواننده <span class="text-xs font-normal text-gray-400">(اختیاری)</span>
                    </label>
                    <textarea name="bio" id="bio" rows="5"
                        placeholder="توضیحات و بیوگرافی خواننده، سال فعالیت، سبک موسیقی و..."
                        class="block w-full py-3 px-4 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm">{{ old('bio') }}</textarea>
                </div>

                {{-- دکمه‌های فرم --}}
                <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-end gap-3">
                    <a href="{{ route('admin.artists.index') }}"
                        class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 transition">
                        انصراف
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>ثبت و ذخیره خواننده</span>
                    </button>
                </div>

            </form>

        </div>

    </div>
@endsection