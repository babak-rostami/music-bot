@extends('layouts.admin')

@section('title', 'جزئیات درخواست')
@section('page_title', 'درخواست: ' . Illuminate\Support\Str::limit($songRequest->query, 40))

@section('header_actions')
    <a href="{{ route('admin.song-requests.index') }}"
        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        بازگشت به لیست
    </a>
@endsection

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- اطلاعات درخواست + تغییر وضعیت --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 p-6">

            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-semibold text-gray-900">اطلاعات درخواست</h3>

                @php
                    $statusClasses = match ($songRequest->status) {
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'processing' => 'bg-blue-100 text-blue-800',
                        'completed' => 'bg-green-100 text-green-800',
                        'rejected' => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-800',
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
                    {{ $songRequest->status_label }}
                </span>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">متن جستجو</p>
                        <p class="text-sm font-medium text-gray-900 break-words">{{ $songRequest->query }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">پلتفرم</p>
                        @if($songRequest->platform === 'telegram')
                            <span class="text-sm font-medium text-sky-700">✈️ تلگرام</span>
                        @elseif($songRequest->platform === 'bale')
                            <span class="text-sm font-medium text-indigo-700">🤖 بله</span>
                        @else
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">تاریخ ثبت</p>
                        <p class="text-sm font-medium text-gray-900">
                            {{ jdate($songRequest->created_at)->format('Y/m/d H:i') }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">بروزرسانی</p>
                        <p class="text-sm font-medium text-gray-900">
                            {{ jdate($songRequest->updated_at)->format('Y/m/d H:i') }}</p>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">یادداشت ادمین</p>
                    <p class="text-sm text-gray-700">{{ $songRequest->admin_note ?? 'بدون یادداشت' }}</p>
                </div>
            </div>

            {{-- تغییر وضعیت + یادداشت --}}
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">تغییر وضعیت و یادداشت</h4>
                <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-3 mb-4 text-xs text-gray-600 leading-6">
                    💡 برای ارسال آهنگ به کاربری که درخواست کرده:
                    <strong>ای دی آهنگ</strong> را در فیلد یادداشت قرار دهید و وضعیت را روی
                    <strong>«آپلود شد»</strong> بگذارید.
                </div>

                <form method="POST" action="{{ route('admin.song-requests.update-status', $songRequest) }}">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">وضعیت جدید</label>
                            <select name="status"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white transition">
                                <option value="pending" @selected($songRequest->status === 'pending')>در انتظار</option>
                                <option value="processing" @selected($songRequest->status === 'processing')>در حال آپلود
                                </option>
                                <option value="completed" @selected($songRequest->status === 'completed')>آپلود شد</option>
                                <option value="rejected" @selected($songRequest->status === 'rejected')>رد شده</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">ای دی آهنگ</label>
                            <input type="text" name="admin_note" value="{{ old('admin_note', $songRequest->admin_note) }}"
                                placeholder="مثلاً: 123"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            ذخیره
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- اطلاعات کاربر --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-5">
                اطلاعات کاربر
                @if($songRequest->platform)
                    <span
                        class="text-xs font-normal text-gray-400">({{ $songRequest->platform === 'telegram' ? 'تلگرام' : 'بله' }})</span>
                @endif
            </h3>

            <div class="space-y-4">

                {{-- پروفایل --}}
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                    <div
                        class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-sm">
                        {{ mb_strtoupper(mb_substr($songRequest->first_name ?? $songRequest->username ?? '?', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $songRequest->full_name }}</p>
                        @if($songRequest->username)
                            <p class="text-xs text-gray-400" dir="ltr">@{{ $songRequest->username }}</p>
                        @endif
                    </div>
                </div>

                {{-- User ID --}}
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">User ID</p>
                    <p class="text-sm font-medium text-gray-900 font-mono" dir="ltr">{{ $songRequest->user_id }}</p>
                </div>

                {{-- Chat ID --}}
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">Chat ID</p>
                    <p class="text-sm font-medium text-gray-900 font-mono" dir="ltr">{{ $songRequest->chat_id }}</p>
                </div>

                {{-- دکمه حذف --}}
                <div class="pt-4 border-t border-gray-200">
                    <form method="POST" action="{{ route('admin.song-requests.destroy', $songRequest) }}"
                        onsubmit="return confirm('آیا از حذف این درخواست مطمئن هستید؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-50 text-red-600 text-sm font-medium rounded-xl hover:bg-red-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            حذف درخواست
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection