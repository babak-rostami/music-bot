@extends('layouts.admin')

@section('title', 'مدیریت کاربران')
@section('page_title', 'مدیریت کاربران')

@section('header_actions')
    <span class="hidden sm:inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg">
        کل: {{ number_format($users->total()) }} کاربر
    </span>
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- فیلتر و جستجو --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap gap-4 items-end">

            {{-- جستجو --}}
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">جستجو</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="نام کاربری، ایمیل، نام..."
                        class="w-full pr-10 pl-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    <svg class="absolute right-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            {{-- نقش --}}
            <div class="w-40">
                <label class="block text-xs text-gray-500 mb-1">نقش</label>
                <select name="role"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white transition">
                    <option value="">همه</option>
                    <option value="admin" @selected(request('role') === 'admin')>ادمین</option>
                    <option value="user" @selected(request('role') === 'user')>کاربر</option>
                </select>
            </div>

            {{-- وضعیت --}}
            <div class="w-40">
                <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
                <select name="status"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white transition">
                    <option value="">همه</option>
                    <option value="1" @selected(request('status') === '1')>فعال</option>
                    <option value="0" @selected(request('status') === '0')>غیرفعال</option>
                </select>
            </div>

            <button type="submit"
                class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                🔍 جستجو
            </button>

            <a href="{{ route('admin.users.index') }}"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                پاک کردن
            </a>
        </form>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- جدول کاربران --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">نام کاربری</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">نام کامل</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">ایمیل</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">نقش</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">وضعیت</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">تاریخ عضویت</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-5">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-8 h-8 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold">
                                        {{ mb_strtoupper(mb_substr($user->full_name ?? $user->username, 0, 1)) }}
                                    </div>
                                    <span class="font-medium text-gray-900">{{ $user->username }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-5 text-gray-600">{{ $user->full_name ?? '—' }}</td>
                            <td class="py-3 px-5 text-gray-600 font-mono text-xs" dir="ltr">{{ $user->email }}</td>
                            <td class="py-3 px-5">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $user->role === 'admin' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $user->display_role }}
                                </span>
                            </td>
                            <td class="py-3 px-5">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $user->is_active ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </td>
                            <td class="py-3 px-5 text-gray-500 whitespace-nowrap">
                                {{ jdate($user->created_at)->format('Y/m/d') }}
                            </td>
                            <td class="py-3 px-5">
                                <div class="flex items-center justify-center gap-2">

                                    {{-- ویرایش --}}
                                    <a href="{{ route('admin.users.edit', $user) }}" title="ویرایش"
                                        class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>

                                    {{-- فعال/غیرفعال --}}
                                    <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="inline">
                                        @csrf
                                        <button type="submit" title="{{ $user->is_active ? 'غیرفعال کردن' : 'فعال کردن' }}"
                                            class="p-2 {{ $user->is_active ? 'text-gray-400 hover:text-yellow-600 hover:bg-yellow-50' : 'text-gray-400 hover:text-green-600 hover:bg-green-50' }} rounded-lg transition-colors">
                                            @if($user->is_active)
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            @endif
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-400">
                                <div class="text-4xl mb-2">👤</div>
                                هیچ کاربری یافت نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- صفحه‌بندی --}}
        @if($users->hasPages())
            <div class="px-5 py-4 border-t border-gray-200">
                {{ $users->links() }}
            </div>
        @endif
    </div>

@endsection