@extends('layouts.admin')

@section('title', 'آهنگ جدید')
@section('page_title', 'افزودن آهنگ جدید')

@section('header_actions')
    <a href="{{ route('admin.songs.index') }}"
        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
        بازگشت به لیست
    </a>
@endsection

@section('content')
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('admin.songs.store') }}">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- اطلاعات اصلی -->
                <div class="space-y-5">
                    <h3 class="text-base font-semibold text-gray-900 pb-2 border-b border-gray-100">اطلاعات اصلی</h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">عنوان آهنگ <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none
                                       {{ $errors->has('title') ? 'border-red-500' : '' }}">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">نام خواننده <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="artist_name" value="{{ old('artist_name') }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none
                                       {{ $errors->has('artist_name') ? 'border-red-500' : '' }}">
                        @error('artist_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">متن آهنگ</label>
                        <textarea name="lyrics" rows="5"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-none">{{ old('lyrics') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">تعداد دانلود</label>
                        <input type="number" name="downloads_count" value="{{ old('downloads_count', 0) }}" min="0"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                </div>

                <!-- اطلاعات فایل -->
                <div class="space-y-5">
                    <h3 class="text-base font-semibold text-gray-900 pb-2 border-b border-gray-100">اطلاعات فایل و دانلود
                    </h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Telegram File ID (128kbps)</label>
                        <input type="text" name="telegram_file_id_128" value="{{ old('telegram_file_id_128') }}" dir="ltr"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Telegram File ID (320kbps)</label>
                        <input type="text" name="telegram_file_id_320" value="{{ old('telegram_file_id_320') }}" dir="ltr"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک دانلود ۱۲۸kbps</label>
                        <input type="url" name="download_link_128" value="{{ old('download_link_128') }}" dir="ltr" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none
                                       {{ $errors->has('download_link_128') ? 'border-red-500' : '' }}">
                        @error('download_link_128') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک دانلود ۳۲۰kbps</label>
                        <input type="url" name="download_link_320" value="{{ old('download_link_320') }}" dir="ltr" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none
                                       {{ $errors->has('download_link_320') ? 'border-red-500' : '' }}">
                        @error('download_link_320') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک ویدیو آپارات</label>
                        <input name="aparat_link" value="{{ old('aparat_link') }}" dir="ltr" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none
                                       {{ $errors->has('aparat_link') ? 'border-red-500' : '' }}">
                        @error('aparat_link') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.songs.index') }}"
                    class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                    انصراف
                </a>
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                    ذخیره آهنگ
                </button>
            </div>
        </form>
    </div>
@endsection