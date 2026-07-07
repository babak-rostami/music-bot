@extends('layouts.admin')

@section('title', 'جزئیات اعلان')
@section('page_title', 'جزئیات اعلان')

@section('content')

    <div class="max-w-3xl mx-auto">

        {{-- دکمه بازگشت --}}
        <div class="mb-4">
            <a href="{{ route('admin.activity-logs.index') }}"
                class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-indigo-600 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                بازگشت به لیست
            </a>
        </div>

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- کارت اصلی --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

            {{-- هدر --}}
            <div class="p-6 border-b border-gray-100 border-r-4 {{ $activityLog->level_border }}">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center text-2xl">
                        {{ $activityLog->type_icon }}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap mb-2">
                            <h1 class="text-lg font-bold text-gray-900">{{ $activityLog->type_label }}</h1>
                            <span
                                class="text-xs font-medium px-2.5 py-1 rounded-full {{ $activityLog->level_badge }}">{{ $activityLog->level_label }}</span>
                            @if ($activityLog->platform)
                                <span
                                    class="text-xs font-medium px-2.5 py-1 rounded-full {{ $activityLog->platform_badge }}">{{ $activityLog->platform_label }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400">
                            {{ jdate($activityLog->created_at)->format('Y/m/d H:i:s') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- متن کامل پیام --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">📝 متن پیام</h3>
                <div
                    class="bg-gray-50 rounded-xl p-4 text-sm text-gray-800 leading-relaxed whitespace-pre-wrap break-words">
                    {{ $activityLog->message }}
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- اطلاعات کاربر --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-xs font-semibold text-gray-500 uppercase mb-3">👤 اطلاعات کاربر</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">

                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-gray-400">شناسه کاربر</span>
                        <span class="font-mono text-gray-900">{{ $activityLog->user_id ?? '—' }}</span>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-gray-400">Chat ID</span>
                        <span class="font-mono text-gray-900">{{ $activityLog->chat_id ?? '—' }}</span>
                    </div>

                    @if ($activityLog->botUser)
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-gray-400">نام</span>
                            <span class="text-gray-900">{{ $activityLog->botUser->full_name }}</span>
                        </div>

                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-gray-400">یوزرنیم</span>
                            <span
                                class="text-gray-900">{{ $activityLog->botUser->username ? '@' . $activityLog->botUser->username : '—' }}</span>
                        </div>

                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-gray-400">وضعیت</span>
                            @if ($activityLog->botUser->is_blocked)
                                <span class="inline-flex items-center gap-1 text-red-600">🚫 بلاک‌شده</span>
                            @else
                                <span class="inline-flex items-center gap-1 text-green-600">✅ فعال</span>
                            @endif
                        </div>

                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-gray-400">تعداد خطای ارسال</span>
                            <span class="text-gray-900">{{ $activityLog->botUser->failed_send_count }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- داده JSON --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            @if (!empty($activityLog->data))
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">🗂 داده‌های اضافی (JSON)</h3>
                    <div class="bg-gray-900 rounded-xl p-4 overflow-x-auto">
                        <pre
                            class="text-xs text-green-400 font-mono">{{ json_encode($activityLog->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- اکشن‌ها --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            <div class="p-6 flex items-center justify-between">
                <div class="text-xs text-gray-400">
                    شناسه رکورد: <span class="font-mono">#{{ $activityLog->id }}</span>
                </div>

                <form method="POST" action="{{ route('admin.activity-logs.destroy', $activityLog) }}"
                    onsubmit="return confirm('این اعلان حذف شود؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-50 text-red-600 text-sm font-medium rounded-xl hover:bg-red-100 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        حذف اعلان
                    </button>
                </form>
            </div>
        </div>
    </div>

@endsection