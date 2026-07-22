@extends('layouts.admin')

@section('title', 'مدیریت لینک‌های دانلود - ' . $song->title)

@push('styles')
    <style>
        .modal-overlay {
            transition: opacity 0.2s ease;
        }

        .modal-content {
            transition: transform 0.2s ease, opacity 0.2s ease;
        }
    </style>
@endpush

@section('page_title', 'مدیریت لینک‌های دانلود')

@section('header_actions')
    <a href="{{ route('admin.songs.index') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        بازگشت به لیست آهنگ‌ها
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        {{-- Song Info Banner --}}
        <div class="rounded-xl bg-gradient-to-l from-blue-50 to-indigo-50 border border-blue-200 p-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-800">لینک‌های دانلود آهنگ: {{ $song->title }}</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        تعداد لینک‌ها: <span class="font-semibold text-gray-700">{{ $downloadLinks->count() }}</span>
                    </p>
                </div>
            </div>
        </div>

        {{-- Download Links Table --}}
        <div class="rounded-xl bg-white border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">سرویس‌دهنده
                                (Provider)</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">کیفیت
                                (Quality)</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">لینک دانلود
                            </th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">اولویت
                                (Priority)</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">
                                عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($downloadLinks as $index => $link)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $index + 1 }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md bg-purple-50 px-2.5 py-1 text-sm font-medium text-purple-700 ring-1 ring-inset ring-purple-600/20">
                                        {{ $link->provider }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($link->quality)
                                        <span
                                            class="inline-flex items-center rounded-md bg-amber-50 px-2.5 py-1 text-sm font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                            {{ $link->quality }} kbps
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-sm">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ $link->download_url }}" target="_blank"
                                        class="text-sm text-blue-600 hover:text-blue-800 hover:underline break-all max-w-xs inline-block"
                                        dir="ltr" title="{{ $link->download_url }}">
                                        {{ Str::limit($link->download_url, 60) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-sm font-semibold text-gray-700">
                                        {{ $link->priority }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        {{-- Edit Button --}}
                                        <button
                                            onclick="openEditModal({{ $link->id }}, '{{ e($link->provider) }}', '{{ e($link->quality) }}', '{{ e($link->download_url) }}', {{ $link->priority }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 transition"
                                            title="ویرایش">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            ویرایش
                                        </button>

                                        {{-- Delete Button --}}
                                        <form action="{{ route('admin.song.download.link.destroy', $link->id) }}" method="POST"
                                            onsubmit="return confirm('آیا از حذف این لینک دانلود مطمئن هستید؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100 transition"
                                                title="حذف">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                حذف
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                        </svg>
                                        <p class="text-gray-500 text-lg">هیچ لینک دانلودی برای این آهنگ ثبت نشده است.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center" role="dialog" aria-modal="true">
        {{-- Overlay --}}
        <div class="modal-overlay absolute inset-0 bg-black/50" onclick="closeEditModal()"></div>

        {{-- Modal Content --}}
        <div class="modal-content relative w-full max-w-lg mx-4 rounded-2xl bg-white shadow-2xl">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-800">ویرایش لینک دانلود</h3>
                <button onclick="closeEditModal()"
                    class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Modal Body --}}
            <form action="{{ route('admin.song.download.link.update', $link->id) }}" id="editForm" method="POST">
                @csrf
                @method('PUT')
                <div class="px-6 py-5 space-y-4">
                    {{-- Provider --}}
                    <div>
                        <label for="edit_provider" class="block text-sm font-medium text-gray-700 mb-1.5">
                            سرویس‌دهنده (Provider)
                        </label>
                        <input type="text" id="edit_provider" name="provider" required dir="ltr"
                            class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition"
                            placeholder="مثلا mybia2music.com">
                    </div>

                    {{-- Quality --}}
                    <div>
                        <label for="edit_quality" class="block text-sm font-medium text-gray-700 mb-1.5">
                            کیفیت (Quality)
                            <span class="text-gray-400 text-xs">(اختیاری)</span>
                        </label>
                        <input type="text" id="edit_quality" name="quality" dir="ltr"
                            class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition"
                            placeholder="مثلا 128 یا 320">
                    </div>

                    {{-- Download URL --}}
                    <div>
                        <label for="edit_download_url" class="block text-sm font-medium text-gray-700 mb-1.5">
                            لینک دانلود
                        </label>
                        <input type="url" id="edit_download_url" name="download_url" required dir="ltr"
                            class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition"
                            placeholder="https://example.com/song.mp3">
                    </div>

                    {{-- Priority --}}
                    <div>
                        <label for="edit_priority" class="block text-sm font-medium text-gray-700 mb-1.5">
                            اولویت (Priority)
                        </label>
                        <input type="number" id="edit_priority" name="priority" min="0" required dir="ltr"
                            class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition"
                            placeholder="0">
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
                    <button type="button" onclick="closeEditModal()"
                        class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 transition">
                        انصراف
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition shadow-sm">
                        ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function openEditModal(id, provider, quality, downloadUrl, priority) {
            const modal = document.getElementById('editModal');
            const form = document.getElementById('editForm');

            // Fill form fields
            document.getElementById('edit_provider').value = provider;
            document.getElementById('edit_quality').value = quality || '';
            document.getElementById('edit_download_url').value = downloadUrl;
            document.getElementById('edit_priority').value = priority;

            // Show modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
@endpush