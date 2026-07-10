@extends('layouts.admin')

@section('title', 'درخواست‌های آپلود')
@section('page_title', 'درخواست‌های آپلود')

@section('header_actions')
    <span class="hidden sm:inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg">
        کل: {{ number_format($stats['all']) }}
    </span>
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- کارت‌های آمار --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <a href="{{ route('admin.song-requests.index', ['status' => 'pending']) }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:border-yellow-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">در انتظار</p>
                    <p class="text-2xl font-bold text-yellow-600 mt-1">{{ number_format($stats['pending']) }}</p>
                </div>
                <div class="w-11 h-11 bg-yellow-50 text-yellow-600 rounded-xl flex items-center justify-center text-xl">⏳
                </div>
            </div>
        </a>

        <a href="{{ route('admin.song-requests.index', ['status' => 'processing']) }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:border-blue-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">در حال آپلود</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($stats['processing']) }}</p>
                </div>
                <div class="w-11 h-11 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl">🔄</div>
            </div>
        </a>

        <a href="{{ route('admin.song-requests.index', ['status' => 'completed']) }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:border-green-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">آپلود شد</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($stats['completed']) }}</p>
                </div>
                <div class="w-11 h-11 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl">✅
                </div>
            </div>
        </a>

        <a href="{{ route('admin.song-requests.index', ['status' => 'rejected']) }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:border-red-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">رد شده</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($stats['rejected']) }}</p>
                </div>
                <div class="w-11 h-11 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-xl">❌</div>
            </div>
        </a>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- جستجو + فیلتر --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
        <form id="searchForm" method="GET" action="{{ route('admin.song-requests.index') }}"
            class="flex flex-wrap gap-4 items-end">

            {{-- جستجو --}}
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">جستجو</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="عنوان آهنگ، نام کاربر..."
                        class="w-full pr-10 pl-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    <svg class="absolute right-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            {{-- فیلتر وضعیت --}}
            <div class="min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
                <select name="status"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white transition">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending" @selected(request('status') === 'pending')>در انتظار</option>
                    <option value="processing" @selected(request('status') === 'processing')>در حال آپلود</option>
                    <option value="completed" @selected(request('status') === 'completed')>آپلود شد</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>رد شده</option>
                </select>
            </div>

            <button type="submit"
                class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                🔍 اعمال
            </button>

            <a href="{{ route('admin.song-requests.index') }}"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                پاک کردن
            </a>
        </form>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- جدول درخواست‌ها --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">متن جستجو</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">کاربر</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">پلتفرم</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">وضعیت</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">یادداشت ادمین</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">تاریخ</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($songRequests as $req)
                                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                        <td class="py-3 px-5">
                                            <p class="font-medium text-gray-900 max-w-[250px] truncate">{{ $req->query }}</p>
                                        </td>
                                        <td class="py-3 px-5">
                                            <p class="text-gray-700">{{ $req->full_name }}</p>
                                            @if($req->username)
                                                <p class="text-xs text-gray-400" dir="ltr">{{ $req->username }}</p>
                                            @endif
                                        </td>
                                        <td class="py-3 px-5">
                                            @if($req->platform === 'telegram')
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">تلگرام</span>
                                            @elseif($req->platform === 'bale')
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">بله</span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-5">
                                            @php
                                                $statusClasses = match ($req->status) {
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'processing' => 'bg-blue-100 text-blue-800',
                                                    'completed' => 'bg-green-100 text-green-800',
                                                    'rejected' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                            @endphp
                         <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
                                                {{ $req->status_label }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-5 text-gray-500 max-w-[200px] truncate">
                                            {{ $req->admin_note ?? '—' }}
                                        </td>
                                        <td class="py-3 px-5 text-gray-500 whitespace-nowrap">
                                            {{ jdate($req->created_at)->ago() }}
                                        </td>
                                        <td class="py-3 px-5">
                                            <div class="flex items-center justify-center gap-1">

                                                {{-- مشاهده / اضافه کردن یادداشت --}}
                                                <a href="{{ route('admin.song-requests.show', $req) }}" title="جزئیات / یادداشت"
                                                    class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </a>

                                                <form method="POST" action="{{ route('admin.song-requests.destroy', $req) }}" class="inline"
                                                    onsubmit="return confirm('آیا از حذف این درخواست مطمئن هستید؟')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="حذف"
                                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-400">
                                <div class="text-4xl mb-2">📭</div>
                                هیچ درخواستی یافت نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($songRequests->hasPages())
            <div class="px-5 py-4 border-t border-gray-200">
                {{ $songRequests->links() }}
            </div>
        @endif
    </div>

@endsection