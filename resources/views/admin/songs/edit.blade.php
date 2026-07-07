@extends('layouts.admin')

@section('title', 'ویرایش آهنگ')
@section('page_title', 'ویرایش: ' . Illuminate\Support\Str::limit($song->title, 40))

@section('header_actions')
    <a href="{{ route('admin.songs.index') }}"
        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        بازگشت به لیست
    </a>
@endsection

@section('content')

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">

        <form method="POST" action="{{ route('admin.songs.update', $song) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- اطلاعات اصلی --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="space-y-5">
                    <h3 class="text-base font-semibold text-gray-900 pb-2 border-b border-gray-100">
                        📝 اطلاعات اصلی
                    </h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">عنوان آهنگ <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title', $song->title) }}"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('title') ? 'border-red-500' : '' }}">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">نام خواننده <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="artist_name" value="{{ old('artist_name', $song->artist_name) }}"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('artist_name') ? 'border-red-500' : '' }}">
                        @error('artist_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">آدرس منبع</label>
                        <input type="url" name="source_url" value="{{ old('source_url', $song->source_url) }}" dir="ltr"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('source_url') ? 'border-red-500' : '' }}">
                        @error('source_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">متن آهنگ</label>
                        <textarea name="lyrics" rows="6"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-none transition">{{ old('lyrics', $song->lyrics) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">تعداد دانلود</label>
                        <input type="number" name="downloads_count"
                            value="{{ old('downloads_count', $song->downloads_count) }}" min="0"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    </div>

                    {{-- تاریخ ثبت (فقط نمایش) --}}
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs text-gray-500 mb-1">تاریخ ثبت</p>
                        <p class="text-sm font-medium text-gray-700">{{ jdate($song->created_at)->format('Y/m/d H:i') }}</p>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- اطلاعات فایل و دانلود --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="space-y-5">
                    <h3 class="text-base font-semibold text-gray-900 pb-2 border-b border-gray-100">
                        📦 اطلاعات فایل و دانلود
                    </h3>

                    {{-- لینک‌های دانلود --}}
                    <div class="bg-gray-50 rounded-xl p-4 space-y-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase">لینک‌های دانلود</p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک دانلود ۱۲۸kbps</label>
                            <input type="url" name="download_link_128"
                                value="{{ old('download_link_128', $song->download_link_128) }}" dir="ltr"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('download_link_128') ? 'border-red-500' : '' }}">
                            @error('download_link_128') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک دانلود ۳۲۰kbps</label>
                            <input type="url" name="download_link_320"
                                value="{{ old('download_link_320', $song->download_link_320) }}" dir="ltr"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('download_link_320') ? 'border-red-500' : '' }}">
                            @error('download_link_320') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- File ID تلگرام --}}
                    <div class="bg-sky-50/40 rounded-xl p-4 space-y-4">
                        <p class="text-xs font-semibold text-sky-700 uppercase">✈️ کش تلگرام (File ID)</p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Telegram File ID (128kbps)</label>
                            <input type="text" name="telegram_file_id_128"
                                value="{{ old('telegram_file_id_128', $song->telegram_file_id_128) }}" dir="ltr"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Telegram File ID (320kbps)</label>
                            <input type="text" name="telegram_file_id_320"
                                value="{{ old('telegram_file_id_320', $song->telegram_file_id_320) }}" dir="ltr"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                        </div>
                    </div>

                    {{-- File ID بله --}}
                    <div class="bg-indigo-50/40 rounded-xl p-4 space-y-4">
                        <p class="text-xs font-semibold text-indigo-700 uppercase">🤖 کش بله (File ID)</p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Bale File ID (128kbps)</label>
                            <input type="text" name="bale_file_id_128"
                                value="{{ old('bale_file_id_128', $song->bale_file_id_128) }}" dir="ltr"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Bale File ID (320kbps)</label>
                            <input type="text" name="bale_file_id_320"
                                value="{{ old('bale_file_id_320', $song->bale_file_id_320) }}" dir="ltr"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                        </div>
                    </div>

                    {{-- آپارات --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک ویدیو آپارات</label>
                        <input type="url" name="aparat_link" value="{{ old('aparat_link', $song->aparat_link) }}" dir="ltr"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('aparat_link') ? 'border-red-500' : '' }}">
                        @error('aparat_link') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- دکمه‌ها --}}
            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.songs.index') }}"
                    class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                    انصراف
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>

@endsection