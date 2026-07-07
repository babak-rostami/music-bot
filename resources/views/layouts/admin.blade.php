<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'پنل مدیریت')</title>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- Vite: فایل‌های CSS و JS (به‌جای CDN) --}}
    {{-- پس از npm run build، فایل‌ها از public/build بارگذاری می‌شوند --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body class="min-h-screen bg-gray-50 text-gray-900">

    <div class="flex min-h-screen">

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- سایدبار (Sidebar) --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <aside
            class="fixed inset-y-0 right-0 z-40 w-64 bg-white border-l border-gray-200 flex flex-col transition-transform lg:translate-x-0 {{ request()->cookie('sidebar') === 'closed' ? 'translate-x-full lg:translate-x-full' : 'translate-x-0' }}">

            {{-- لوگو --}}
            <div class="h-16 flex items-center gap-2 px-6 border-b border-gray-100">
                <div
                    class="w-9 h-9 bg-indigo-600 text-white rounded-xl flex items-center justify-center text-lg font-bold">
                    🎵
                </div>
                <span class="font-bold text-gray-900">پنل مدیریت</span>
            </div>

            {{-- منوی ناوبری --}}
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">

                <span class="block px-3 py-2 text-xs font-semibold text-gray-400 uppercase">منو</span>

                <a href="{{ route('admin.dashboard') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                        {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <span>📊</span> داشبورد
                </a>

                <a href="{{ route('admin.songs.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                        {{ request()->routeIs('admin.songs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <span>🎵</span> آهنگ‌ها
                </a>

                <a href="{{ route('admin.song-requests.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                        {{ request()->routeIs('admin.song-requests.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <span>📥</span> درخواست‌ها
                </a>

                <a href="{{ route('admin.activity-logs.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                        {{ request()->routeIs('admin.activity-logs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <span>🔔</span> اعلانات
                </a>
            </nav>

            {{-- خروج --}}
            <div class="p-3 border-t border-gray-100">
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-red-600 hover:bg-red-50 transition">
                        <span>🚪</span> خروج
                    </button>
                </form>
            </div>
        </aside>

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- محتوای اصلی --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <div class="flex-1 lg:mr-64">

            {{-- هدر بالای صفحه --}}
            <header
                class="sticky top-0 z-30 h-16 bg-white/80 backdrop-blur border-b border-gray-200 flex items-center justify-between px-6">
                <h1 class="text-lg font-bold text-gray-900">
                    @yield('page_title', 'پنل مدیریت')
                </h1>

                <div class="flex items-center gap-3">
                    {{-- دکمه منو در موبایل --}}
                    <button onclick="document.querySelector('aside').classList.toggle('translate-x-full')"
                        class="lg:hidden p-2 text-gray-500 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </header>

            {{-- بدنه صفحه --}}
            <main class="p-6">

                {{-- پیام‌های فلش (عمومی) --}}
                @if (session('success'))
                    <div data-auto-dismiss="5"
                        class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
                        ✅ {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div data-auto-dismiss="5"
                        class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
                        ❌ {{ session('error') }}
                    </div>
                @endif

                {{-- محتوای هر صفحه --}}
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>

</html>