@extends('layouts.admin')

@section('title', 'جزئیات درخواست')
@section('page_title', 'درخواست: ' . Str::limit($songRequest->query, 40))

@section('header_actions')
    <a href="{{ route('admin.song-requests.index') }}"
        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
        بازگشت به لیست
    </a>
@endsection

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- اطلاعات درخواست -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-5">اطلاعات درخواست</h3>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">متن جستجو</p>
                        <p class="text-sm font-medium text-gray-900">{{ $songRequest->query }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">وضعیت</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $songRequest->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $songRequest->status === 'processing' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $songRequest->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $songRequest->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                ">{{ $songRequest->status_label }}</span>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">تاریخ ثبت</p>
                        <p class="text-sm font-medium text-gray-900">{{ $songRequest->created_at->format('Y/m/d H:i') }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">بروزرسانی</p>
                        <p class="text-sm font-medium text-gray-900">{{ $songRequest->updated_at->format('Y/m/d H:i') }}</p>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">یادداشت ادمین</p>
                    <p class="text-sm text-gray-700">{{ $songRequest->admin_note ?? 'بدون یادداشت' }}</p>
                </div>
            </div>

            <!-- تغییر وضعیت + یادداشت -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-900 mb-4">تغییر وضعیت و یادداشت</h4>
                <p>برای ارسال آهنگ به کاربری که درخواست کرده:</p>
                <p>ای دی آهنگ بذار و وضعیت آپلود شد کن</p>
                <form method="POST" action="{{ route('admin.song-requests.update-status', $songRequest) }}">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">وضعیت جدید</label>
                            <select name="status"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white">
                                <option value="pending" {{ $songRequest->status === 'pending' ? 'selected' : '' }}>در انتظار
                                </option>
                                <option value="processing" {{ $songRequest->status === 'processing' ? 'selected' : '' }}>در
                                    حال آپلود</option>
                                <option value="completed" {{ $songRequest->status === 'completed' ? 'selected' : '' }}>آپلود
                                    شد</option>
                                <option value="rejected" {{ $songRequest->status === 'rejected' ? 'selected' : '' }}>رد شده
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">ای دی آهنگ</label>
                            <input type="text" name="admin_note" value="{{ old('admin_note', $songRequest->admin_note) }}"
                                placeholder="یادداشت اختیاری..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit"
                            class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                            ذخیره
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- اطلاعات کاربر تلگرام -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-5">اطلاعات کاربر تلگرام</h3>
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                    <div
                        class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-sm">
                        {{ mb_strtoupper(mb_substr($songRequest->first_name ?? $songRequest->username ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $songRequest->full_name }}</p>
                        @if($songRequest->username)
                            <p class="text-xs text-gray-400" dir="ltr">@{{ $songRequest->username }}</p>
                        @endif
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">User ID</p>
                    <p class="text-sm font-medium text-gray-900" dir="ltr">{{ $songRequest->user_id }}</p>
                </div>

                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">Chat ID</p>
                    <p class="text-sm font-medium text-gray-900" dir="ltr">{{ $songRequest->chat_id }}</p>
                </div>

                <!-- دکمه حذف -->
                <div class="pt-4 border-t border-gray-200">
                    <form method="POST" action="{{ route('admin.song-requests.destroy', $songRequest) }}"
                        onsubmit="return confirm('آیا از حذف این درخواست مطمئن هستید؟')">
                        @csrf @method('DELETE')
                        <button type="submit"
                            class="w-full px-4 py-2.5 bg-red-50 text-red-600 text-sm font-medium rounded-xl hover:bg-red-100 transition-colors">
                            حذف درخواست
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection