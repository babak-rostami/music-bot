@extends('layouts.admin')

@section('title', 'مدیریت نظرات')
@section('page_title', 'نظرات کاربران')

@section('header_actions')
    <span class="hidden sm:inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg">
        کل: {{ number_format($stats['total']) }}
    </span>
@endsection

@section('content')

    @if (session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <a href="{{ route('admin.comments.index') }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:border-indigo-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">کل نظرات (اصلی + پاسخ)</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total']) }}</p>
                </div>
                <div class="w-11 h-11 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl">💬</div>
            </div>
        </a>

        <a href="{{ route('admin.comments.index', ['status' => 1]) }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:border-green-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">تایید‌شده / نمایش داده می‌شن</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($stats['approved']) }}</p>
                </div>
                <div class="w-11 h-11 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl">✅</div>
            </div>
        </a>

        <a href="{{ route('admin.comments.index', ['status' => 0]) }}"
            class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:border-red-300 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">مخفی‌شده</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($stats['hidden']) }}</p>
                </div>
                <div class="w-11 h-11 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-xl">🚫</div>
            </div>
        </a>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">پاسخ‌ها</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['replies']) }}</p>
                </div>
                <div class="w-11 h-11 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl">↩️</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('admin.comments.index') }}" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">جستجو</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="نام یا متن نظر..."
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
            </div>

            <div class="min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
                <select name="status"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white transition">
                    <option value="">همه</option>
                    <option value="1" @selected(request('status') === '1')>تایید‌شده</option>
                    <option value="0" @selected(request('status') === '0')>مخفی‌شده</option>
                </select>
            </div>

            <button type="submit"
                class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                🔍 اعمال
            </button>
            <a href="{{ route('admin.comments.index') }}"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                پاک کردن
            </a>
        </form>
    </div>

    <form id="bulk-delete-form" method="POST" action="{{ route('admin.comments.bulk-destroy') }}"
        onsubmit="return confirm('نظرات انتخاب‌شده حذف شوند؟ پاسخ‌های زیر این نظرات هم پاک می‌شن.');">
        @csrf
        @method('DELETE')
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 bg-gray-50/60">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" id="select-all"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>انتخاب همه (این صفحه)</span>
            </label>

            <button type="submit" form="bulk-delete-form" id="bulk-delete-btn"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                disabled>
                🗑 حذف انتخاب‌شده‌ها
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="w-10 py-3 px-5"></th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">نظر</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">آهنگ</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">واکنش‌ها</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">وضعیت</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">تاریخ</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comments as $comment)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-5">
                                <input type="checkbox" name="ids[]" value="{{ $comment->id }}" form="bulk-delete-form"
                                    class="row-check h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="py-3 px-5 max-w-[320px]">
                                <p class="font-medium text-gray-900">{{ $comment->name }}</p>
                                <p class="text-gray-500 truncate">{{ $comment->body }}</p>
                                @if($comment->replies_count > 0)
                                    <span class="text-xs text-blue-600">↩ {{ $comment->replies_count }} پاسخ</span>
                                @endif
                            </td>
                            <td class="py-3 px-5">
                                @if($comment->song)
                                    <a href="{{"https://saz.becharkh.com/songs/". $comment->song->slug}}"  target="_blank"
                                        class="text-indigo-600 hover:underline">{{ $comment->song->title }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-5 text-center whitespace-nowrap text-gray-500">
                                👍 {{ $comment->likes_count }} &nbsp; 👎 {{ $comment->dislikes_count }}
                            </td>
                            <td class="py-3 px-5">
                                @if($comment->status)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">تایید‌شده</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">مخفی</span>
                                @endif
                            </td>
                            <td class="py-3 px-5 text-gray-500 whitespace-nowrap">
                                {{ jdate($comment->created_at)->ago() }}
                            </td>
                            <td class="py-3 px-5">
                                <div class="flex items-center justify-center gap-1">
                                    <form method="POST" action="{{ route('admin.comments.update-status', $comment) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $comment->status ? 0 : 1 }}">
                                        <button type="submit" title="{{ $comment->status ? 'مخفی کردن' : 'تایید کردن' }}"
                                            class="p-1.5 {{ $comment->status ? 'text-gray-400 hover:text-red-600 hover:bg-red-50' : 'text-gray-400 hover:text-green-600 hover:bg-green-50' }} rounded-lg transition-colors">
                                            {{ $comment->status ? '🚫' : '✅' }}
                                        </button>
                                    </form>

                                    <button type="button"
                                        onclick="openCommentEditModal({{ $comment->id }}, '{{ e($comment->name) }}', {{ \Illuminate\Support\Js::from($comment->body) }}, {{ $comment->status ? 1 : 0 }})"
                                        title="ویرایش"
                                        class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                        ✏️
                                    </button>

                                    <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}" class="inline"
                                        onsubmit="return confirm('این نظر حذف شود؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="حذف"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            🗑
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-400">
                                <div class="text-4xl mb-2">💬</div>
                                هیچ نظری یافت نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($comments->hasPages())
            <div class="px-5 py-4 border-t border-gray-200">
                {{ $comments->links() }}
            </div>
        @endif
    </div>

    {{-- مودال ویرایش --}}
    <div id="commentEditModal" class="fixed inset-0 z-50 hidden items-center justify-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" onclick="closeCommentEditModal()"></div>

        <div class="relative w-full max-w-lg mx-4 rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-800">ویرایش نظر</h3>
                <button onclick="closeCommentEditModal()" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="commentEditForm" method="POST">
                @csrf
                @method('PUT')
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">نام</label>
                        <input type="text" name="name" id="edit_comment_name" required
                            class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">متن نظر</label>
                        <textarea name="body" id="edit_comment_body" rows="4" required
                            class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">وضعیت</label>
                        <select name="status" id="edit_comment_status"
                            class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition bg-white">
                            <option value="1">تایید‌شده (نمایش داده بشه)</option>
                            <option value="0">مخفی</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
                    <button type="button" onclick="closeCommentEditModal()"
                        class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 transition">
                        انصراف
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition shadow-sm">
                        ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function openCommentEditModal(id, name, body, status) {
            const modal = document.getElementById('commentEditModal');
            const form = document.getElementById('commentEditForm');

            form.action = `{{ url('admin_page/comments') }}/${id}`;
            document.getElementById('edit_comment_name').value = name;
            document.getElementById('edit_comment_body').value = body;
            document.getElementById('edit_comment_status').value = String(status);

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeCommentEditModal() {
            const modal = document.getElementById('commentEditModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeCommentEditModal();
        });

        (function () {
            const selectAll = document.getElementById('select-all');
            const bulkBtn = document.getElementById('bulk-delete-btn');
            const checks = () => Array.from(document.querySelectorAll('.row-check'));

            function syncBulkBtn() {
                const any = checks().some((c) => c.checked);
                if (bulkBtn) bulkBtn.disabled = !any;
            }

            selectAll?.addEventListener('change', function () {
                checks().forEach((c) => { c.checked = selectAll.checked; });
                syncBulkBtn();
            });

            document.addEventListener('change', function (e) {
                if (e.target.classList?.contains('row-check')) {
                    const all = checks();
                    if (selectAll) selectAll.checked = all.length > 0 && all.every((c) => c.checked);
                    syncBulkBtn();
                }
            });
        })();
    </script>
@endpush