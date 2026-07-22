@extends('layouts.admin')

@section('title', 'آهنگ‌ها')
@section('page_title', 'مدیریت آهنگ‌ها')

@section('header_actions')
    <span class="hidden sm:inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg">
        کل: {{ number_format($songs->total()) }} آهنگ
    </span>
    <a href="{{ route('admin.songs.create') }}"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        آهنگ جدید
    </a>
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- جستجو --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
        <button onclick="openLinkTesterModal()"
            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 transition"
            title="تست لینک">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            تست لینک
        </button>
        <form method="GET" action="{{ route('admin.songs.index') }}" class="flex flex-wrap gap-4 items-end">

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">جستجو</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="عنوان، خواننده، آدرس..."
                        class="w-full pr-10 pl-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    <svg class="absolute right-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <button type="submit"
                class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                🔍 جستجو
            </button>

            <a href="{{ route('admin.songs.index') }}"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                پاک کردن
            </a>
        </form>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- جدول آهنگ‌ها --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">شناسه</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">عنوان</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">خواننده</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">دانلود</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">متن</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">۱۲۸</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">۳۲۰</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">تاریخ</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($songs as $song)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-5 font-mono text-gray-400 text-xs">{{ $song->id }}</td>
                            <td class="py-3 px-5 font-medium text-gray-900 max-w-[200px] truncate">{{ $song->title }}</td>
                            <td class="py-3 px-5 text-gray-600">{{ $song->artist_name }}</td>
                            <td class="py-3 px-5 text-gray-500 whitespace-nowrap">{{ number_format($song->downloads_count) }}
                            </td>

                            {{-- متن آهنگ --}}
                            <td class="py-3 px-5 text-center">
                                @if($song->lyrics)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            {{-- لینک ۱۲۸ --}}
                            <td class="py-3 px-5 text-center">
                                @if($song->download_link_128)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            {{-- لینک ۳۲۰ --}}
                            <td class="py-3 px-5 text-center">
                                @if($song->download_link_320)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            <td class="py-3 px-5 text-gray-500 whitespace-nowrap">
                                {{ jdate($song->created_at)->ago() }}
                            </td>

                            <td class="py-3 px-5">
                                <div class="flex items-center justify-center gap-2">
                                    {{-- نمایش --}}
                                    <a href="https://music.becharkh.com/songs/{{ $song->slug }}" target="_blank" rel="noopener"
                                        title="نمایش"
                                        class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7S3.732 16.057 2.458 12z" />
                                        </svg>
                                    </a>
                                    {{-- لینک های دانلود --}}
                                    <a href="{{route('admin.song.download.link.index', $song->id)}}" target="_blank"
                                        rel="noopener" title="لینک های دانلود"
                                        class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors">
                                        🔗
                                    </a>
                                    {{-- ویرایش --}}
                                    <a href="{{ route('admin.songs.edit', $song) }}" title="ویرایش"
                                        class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    {{-- حذف --}}
                                    <form action="{{ route('admin.songs.destroy', $song) }}" method="POST"
                                        onsubmit="return confirm('آیا از حذف این آهنگ مطمئن هستید؟');">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" title="حذف"
                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7L18.132 19.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.994-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-400">
                                <div class="text-4xl mb-2">🎵</div>
                                هیچ آهنگی یافت نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($songs->hasPages())
            <div class="px-5 py-4 border-t border-gray-200">
                {{ $songs->links() }}
            </div>
        @endif
    </div>



    {{-- ============================================= --}}
    {{-- مودال تست لینک --}}
    {{-- ============================================= --}}

    <div id="linkTesterModal" class="fixed inset-0 z-50 hidden items-center justify-center" role="dialog" aria-modal="true">
        {{-- Overlay --}}
        <div class="absolute inset-0 bg-black/50" onclick="closeLinkTesterModal()"></div>

        {{-- Modal Content --}}
        <div class="relative w-full max-w-lg mx-4 rounded-2xl bg-white shadow-2xl">
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-800">🔗 تست لینک دانلود</h3>
                <button onclick="closeLinkTesterModal()"
                    class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-4">
                {{-- URL Input --}}
                <div id="linkTesterInput">
                    <label for="linkTesterUrl" class="block text-sm font-medium text-gray-700 mb-1.5">
                        لینک مورد نظر
                    </label>
                    <div class="flex gap-2">
                        <input type="url" id="linkTesterUrl" dir="ltr"
                            class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition"
                            placeholder="https://example.com/file.mp3" onkeydown="if(event.key==='Enter'){runLinkTest()}">
                        <button onclick="runLinkTest()" id="linkTesterBtn"
                            class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 transition shadow-sm whitespace-nowrap">
                            تست کن
                        </button>
                    </div>
                </div>

                {{-- Loading State --}}
                <div id="linkTesterLoading" class="hidden">
                    <div class="flex flex-col items-center gap-4 py-6">
                        <div class="w-12 h-12 rounded-full border-4 border-emerald-100 border-t-emerald-600 animate-spin">
                        </div>
                        <p class="text-sm text-gray-600 font-medium">در حال بررسی لینک...</p>
                        <p class="text-xs text-gray-400">لطفاً صبر کنید</p>
                    </div>
                </div>

                {{-- Success State --}}
                <div id="linkTesterSuccess" class="hidden">
                    <div class="flex flex-col items-center gap-3 py-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
                            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <p class="text-lg font-bold text-green-700">لینک سالم است ✓</p>
                    </div>
                    <div id="linkTesterSuccessDetails" class="rounded-lg bg-green-50 border border-green-200 p-4 space-y-2">
                    </div>
                </div>

                {{-- Error State --}}
                <div id="linkTesterError" class="hidden">
                    <div class="flex flex-col items-center gap-3 py-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
                            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <p id="linkTesterErrorMsg" class="text-lg font-bold text-red-700">لینک در دسترس نیست</p>
                    </div>
                    <div id="linkTesterErrorDetails" class="rounded-lg bg-red-50 border border-red-200 p-4 space-y-2"></div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
                <button onclick="closeLinkTesterModal()"
                    class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 transition">
                    بستن
                </button>
            </div>
        </div>
    </div>


@endsection

@push('scripts')
    <script>
        function openLinkTesterModal() {
            const modal = document.getElementById('linkTesterModal');

            // Reset states
            document.getElementById('linkTesterLoading').classList.add('hidden');
            document.getElementById('linkTesterSuccess').classList.add('hidden');
            document.getElementById('linkTesterError').classList.add('hidden');
            document.getElementById('linkTesterInput').classList.remove('hidden');
            document.getElementById('linkTesterBtn').disabled = false;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';

            // Focus input
            setTimeout(() => document.getElementById('linkTesterUrl').focus(), 100);
        }

        function closeLinkTesterModal() {
            const modal = document.getElementById('linkTesterModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        function runLinkTest() {
            const url = document.getElementById('linkTesterUrl').value.trim();

            if (!url) {
                alert('لطفاً یک لینک وارد کنید.');
                return;
            }

            // Show loading, hide results
            document.getElementById('linkTesterLoading').classList.remove('hidden');
            document.getElementById('linkTesterSuccess').classList.add('hidden');
            document.getElementById('linkTesterError').classList.add('hidden');
            document.getElementById('linkTesterBtn').disabled = true;

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch('/admin_page/test-link', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ url: url }),
            })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('linkTesterLoading').classList.add('hidden');
                    document.getElementById('linkTesterBtn').disabled = false;

                    if (data.success) {
                        showLinkTestSuccess(data);
                    } else {
                        showLinkTestError(data);
                    }
                })
                .catch(error => {
                    document.getElementById('linkTesterLoading').classList.add('hidden');
                    document.getElementById('linkTesterBtn').disabled = false;
                    showLinkTestError({
                        error: 'خطای شبکه',
                        details: {
                            message: error.message || 'خطا در ارتباط با سرور',
                            type: 'network_error',
                        },
                    });
                });
        }

        function showLinkTestSuccess(data) {
            const detailsDiv = document.getElementById('linkTesterSuccessDetails');
            let html = '';

            if (data.details) {
                if (data.details.status_code) {
                    html += `<div class="flex justify-between text-sm"><span class="text-gray-600">Status Code:</span><span class="font-mono font-semibold text-green-700">${data.details.status_code}</span></div>`;
                }
                if (data.details.content_type) {
                    html += `<div class="flex justify-between text-sm"><span class="text-gray-600">Content-Type:</span><span class="font-mono text-xs text-green-700" dir="ltr">${data.details.content_type}</span></div>`;
                }
                if (data.details.content_length) {
                    html += `<div class="flex justify-between text-sm"><span class="text-gray-600">حجم فایل:</span><span class="font-semibold text-green-700">${data.details.content_length}</span></div>`;
                }
                if (data.details.response_time) {
                    html += `<div class="flex justify-between text-sm"><span class="text-gray-600">زمان پاسخ:</span><span class="font-semibold text-green-700">${data.details.response_time}</span></div>`;
                }
            }

            detailsDiv.innerHTML = html;
            document.getElementById('linkTesterSuccess').classList.remove('hidden');
        }

        function showLinkTestError(data) {
            const msgEl = document.getElementById('linkTesterErrorMsg');
            const detailsDiv = document.getElementById('linkTesterErrorDetails');

            msgEl.textContent = data.error || 'لینک در دسترس نیست';

            let html = '';
            if (data.details) {
                if (data.details.status_code) {
                    html += `<div class="flex justify-between text-sm"><span class="text-gray-600">Status Code:</span><span class="font-mono font-semibold text-red-700">${data.details.status_code}</span></div>`;
                }
                if (data.details.message) {
                    html += `<div class="mt-2"><span class="block text-sm text-gray-600 mb-1">جزئیات خطا:</span><p class="text-sm text-red-600 bg-red-100 rounded p-2 font-mono text-xs break-all" dir="ltr">${data.details.message}</p></div>`;
                }
                if (data.details.type) {
                    const labels = {
                        'connection_error': '🔌 خطای اتصال به سرور مقصد',
                        'network_error': '🌐 خطای شبکه',
                        'timeout_error': '⏱️ پایان مهلت اتصال (Timeout)',
                        'dns_error': '🔍 دامنه پیدا نشد',
                        'ssl_error': '🔒 خطای SSL',
                        'unknown_error': '❓ خطای ناشناخته',
                    };
                    html += `<div class="mt-2 text-sm"><span class="text-gray-600">نوع خطا:</span> <span class="font-semibold text-red-700">${labels[data.details.type] || data.details.type}</span></div>`;
                }
            }

            detailsDiv.innerHTML = html;
            document.getElementById('linkTesterError').classList.remove('hidden');
        }

        // Close with Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeLinkTesterModal();
            }
        });
    </script>
@endpush