@extends('layouts.admin')

@section('title', 'جزئیات کاربر: ' . $botUser->full_name)

@section('page_title', 'پروفایل و جزئیات کاربر ربات')

@section('header_actions')
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.bot-users.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                </path>
            </svg>
            <span>بازگشت به لیست کاربران</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="space-y-6 text-right" dir="rtl">

        {{-- نمایش پیام‌ها --}}
        @if(session('success'))
            <div
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-900/30 dark:border-emerald-800 dark:text-emerald-300 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <p class="font-medium text-sm">{{ session('success') }}</p>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div
                class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-900/30 dark:border-rose-800 dark:text-rose-300 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="font-medium text-sm">{{ session('error') }}</p>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ستون سمت راست: کارت پروفایل اصلی --}}
            <div class="lg:col-span-1 space-y-6">
                <div
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-6 text-center">

                    {{-- آواتار --}}
                    <div
                        class="w-24 h-24 mx-auto rounded-full flex items-center justify-center font-bold text-3xl shadow-inner mb-4
                                            {{ $botUser->platform === \App\Models\BotUser::PLATFORM_TELEGRAM ? 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300' }}">
                        {{ mb_substr($botUser->first_name ?? $botUser->platform_label, 0, 1, 'UTF-8') }}
                    </div>

                    {{-- نام و نام کاربری --}}
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $botUser->full_name }}</h3>
                    @if($botUser->username)
                        <a href="{{ $botUser->profile_url }}" target="_blank"
                            class="text-sm text-indigo-600 hover:underline dark:text-indigo-400 dir-ltr inline-block mt-1">
                            {{ '@@' . ltrim($botUser->username, '@') }}
                        </a>
                    @else
                        <p class="text-xs text-gray-400 mt-1">بدون شناسه کاربری (Username)</p>
                    @endif

                    {{-- بج پلتفرم --}}
                    <div class="mt-4 flex justify-center">
                        @if($botUser->platform === \App\Models\BotUser::PLATFORM_TELEGRAM)
                            <span
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-800">
                                <svg class="w-4 h-4 text-sky-500 fill-current" viewBox="0 0 24 24">
                                    <path
                                        d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                                </svg>
                                <span>کاربر تلگرام (Telegram Bot)</span>
                            </span>
                        @elseif($botUser->platform === \App\Models\BotUser::PLATFORM_BALE)
                            <span
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                    </path>
                                </svg>
                                <span>کاربر بله (Bale Bot)</span>
                            </span>
                        @endif
                    </div>

                    <hr class="my-6 border-gray-100 dark:border-gray-700">

                    {{-- دکمه‌های عملیات سریع --}}
                    <div class="space-y-3">
                        <form action="{{ route('admin.bot-users.toggle-block', $botUser->id) }}" method="POST"
                            onsubmit="return confirm('{{ $botUser->is_blocked ? 'آیا از رفع مسدودیت این کاربر اطمینان دارید؟' : 'آیا از مسدود کردن این کاربر اطمینان دارید؟' }}');">
                            @csrf
                            <button type="submit"
                                class="w-full py-2.5 px-4 rounded-xl text-sm font-medium transition shadow-sm flex items-center justify-center gap-2
                                                       {{ $botUser->is_blocked ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if($botUser->is_blocked)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2z">
                                        </path>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                        </path>
                                    @endif
                                </svg>
                                <span>{{ $botUser->is_blocked ? 'رفع مسدودیت کاربر (Unblock)' : 'مسدود کردن کاربر (Block)' }}</span>
                            </button>
                        </form>

                        @if($botUser->failed_send_count > 0)
                            <form action="{{ route('admin.bot-users.reset-failed', $botUser->id) }}" method="POST"
                                onsubmit="return confirm('آیا از صفر کردن خطاهای ارسال اطمینان دارید؟');">
                                @csrf
                                <button type="submit"
                                    class="w-full py-2.5 px-4 rounded-xl text-sm font-medium bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 transition dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800 flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                        </path>
                                    </svg>
                                    <span>بازنشانی خطاهای ارسال ({{ $botUser->failed_send_count }})</span>
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            </div>

            {{-- ستون سمت چپ: اطلاعات هویتی، وضعیت تعامل و فرم ارسال پیام --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- کارت اطلاعات دقیق کاربر --}}
                <div
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-6">
                    <h4
                        class="font-bold text-gray-800 dark:text-white pb-4 border-b border-gray-100 dark:border-gray-700 text-base flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span>اطلاعات دقیق کاربر و وضعیت سیستم</span>
                    </h4>

                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pt-4 text-sm">

                        <div class="p-3.5 bg-gray-50 dark:bg-gray-700/40 rounded-xl">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">شناسه سیستم (ID)</dt>
                            <dd class="mt-1 font-mono font-bold text-gray-800 dark:text-gray-200">{{ $botUser->id }}</dd>
                        </div>

                        <div class="p-3.5 bg-gray-50 dark:bg-gray-700/40 rounded-xl">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">شناسه پلتفرم (user_id)</dt>
                            <dd
                                class="mt-1 font-mono font-bold text-gray-800 dark:text-gray-200 flex items-center justify-between">
                                <span>{{ $botUser->user_id }}</span>
                                <button type="button"
                                    onclick="navigator.clipboard.writeText('{{ $botUser->user_id }}'); alert('شناسه کپی شد!');"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-sans">
                                    کپی شناسه
                                </button>
                            </dd>
                        </div>

                        <div class="p-3.5 bg-gray-50 dark:bg-gray-700/40 rounded-xl">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">نام (First Name)</dt>
                            <dd class="mt-1 font-semibold text-gray-800 dark:text-gray-200">
                                {{ $botUser->first_name ?? '---' }}
                            </dd>
                        </div>

                        <div class="p-3.5 bg-gray-50 dark:bg-gray-700/40 rounded-xl">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">نام خانوادگی (Last Name)</dt>
                            <dd class="mt-1 font-semibold text-gray-800 dark:text-gray-200">
                                {{ $botUser->last_name ?? '---' }}
                            </dd>
                        </div>

                        <div class="p-3.5 bg-gray-50 dark:bg-gray-700/40 rounded-xl">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">وضعیت فعلی در ربات</dt>
                            <dd class="mt-1 flex items-center gap-2">
                                @if($botUser->is_blocked)
                                    <span
                                        class="px-2 py-0.5 text-xs font-semibold rounded bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">مسدود
                                        شده</span>
                                    @if($botUser->blocked_at)
                                        <span class="text-xs text-gray-400">({{ jdate($botUser->blocked_at)->ago() }})</span>
                                    @endif
                                @else
                                    <span
                                        class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">فعال
                                        (Active)</span>
                                @endif
                            </dd>
                        </div>

                        <div class="p-3.5 bg-gray-50 dark:bg-gray-700/40 rounded-xl">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">تعداد خطای ارسال پیام
                                (failed_send_count)</dt>
                            <dd class="mt-1 font-semibold text-gray-800 dark:text-gray-200">
                                {{ number_format($botUser->failed_send_count) }} بار خطا در ارسال پیام
                            </dd>
                        </div>

                        <div class="p-3.5 bg-gray-50 dark:bg-gray-700/40 rounded-xl">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">آخرین فعالیت (Last Activity)
                            </dt>
                            <dd class="mt-1 font-semibold text-gray-800 dark:text-gray-200">
                                {{ $botUser->last_activity_at ? jdate($botUser->last_activity_at)->ago() : 'نامشخص' }}
                            </dd>
                        </div>

                        <div class="p-3.5 bg-gray-50 dark:bg-gray-700/40 rounded-xl">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">تاریخ اولین ثبت‌نام (Created
                                At)</dt>
                            <dd class="mt-1 font-semibold text-gray-800 dark:text-gray-200">
                                {{ jdate($botUser->created_at)->ago() }}
                            </dd>
                        </div>

                    </dl>
                </div>

                {{-- بخش جدید: فرم ارسال پیام و آهنگ به کاربر ربات --}}
                <div
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700 mb-6">
                        <h4 class="font-bold text-gray-800 dark:text-white text-base flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            <span>ارسال مستقیم پیام یا آهنگ به کاربر (در {{ $botUser->platform_label }})</span>
                        </h4>
                        <span
                            class="px-3 py-1 rounded-full text-xs font-semibold {{ $botUser->platform === \App\Models\BotUser::PLATFORM_TELEGRAM ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' }}">
                            {{ $botUser->platform_label }}
                        </span>
                    </div>

                    <form action="{{ route('admin.bot-users.send-message', $botUser->id) }}" method="POST"
                        class="space-y-6">
                        @csrf

                        {{-- ۱. شناسه آهنگ (ID) --}}
                        <div>
                            <label for="song_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                شناسه آهنگ (Song ID) جهت آپلود و ارسال:
                            </label>
                            <div class="relative">
                                <input type="number" name="song_id" id="song_id" value="{{ old('song_id') }}" min="0"
                                    placeholder="مثلاً: 14580 (اگر خالی بگذارید یا 0 باشد، آهنگی ارسال نمی‌شود)"
                                    class="block w-full py-3 px-4 text-sm font-mono border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition shadow-sm">
                            </div>

                            {{-- هشدار ویژه کیفیت 320 طبق خواسته کاربر --}}
                            <div
                                class="mt-2.5 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-950/40 dark:border-amber-800/80 dark:text-amber-300 flex items-start gap-2.5 text-xs leading-relaxed shadow-sm">
                                <svg class="w-5 h-5 flex-shrink-0 text-amber-500 mt-0.5" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                    </path>
                                </svg>
                                <div>
                                    <span class="font-bold block text-amber-900 dark:text-amber-200 mb-0.5">توجه به کیفیت
                                        آهنگ:</span>
                                    <span>این قابلیت و Job طراحی‌شده فقط برای ارسال کیفیت <strong>320</strong> تنظیم شده
                                        است! لطفاً حواستان باشد در صورتی که کیفیت آهنگ <strong>128</strong> است، شناسه‌ی آن
                                        را در اینجا وارد نکنید.</span>
                                </div>
                            </div>
                        </div>

                        {{-- ۲. متن پیام (Message) --}}
                        <div>
                            <label for="message" class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                متن پیام ارسالی همراه با آهنگ (یا به تنهایی):
                            </label>
                            <textarea name="message" id="message" rows="3"
                                placeholder="متن پیام دلخواه شما... (خالی بگذارید تا پیام پیش‌فرض ارسال شود)"
                                class="block w-full py-3 px-4 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition shadow-sm">{{ old('message') }}</textarea>

                            {{-- راهنمای قوانین پیام --}}
                            <div
                                class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-1 bg-gray-50 dark:bg-gray-700/30 p-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                    <span>اگر <strong>متنی بنویسید</strong>: دقیقاً همان متن به کاربر ارسال خواهد شد.</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>اگر <strong>خالی بگذارید</strong>: پیام پیش‌فرض (<code
                                            class="text-rose-600 dark:text-rose-400 font-sans">❤️آهنگی که میخواستی برات آپلود کردم</code>)
                                        ارسال می‌شود.</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    <span>اگر فقط عدد <strong>0</strong> را وارد کنید: هیچ پیام متنی ارسال نخواهد شد (فقط
                                        آهنگ در صورت وجود آی‌دی ارسال می‌شود).</span>
                                </div>
                            </div>
                        </div>

                        {{-- دکمه ارسال --}}
                        <div class="pt-2">
                            <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 rounded-xl shadow-md hover:shadow-lg transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                <span>ارسال دستور به ربات ({{ $botUser->platform_label }})</span>
                            </button>
                        </div>

                    </form>
                </div>

            </div>

        </div>

    </div>
@endsection