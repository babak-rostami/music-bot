@extends('layouts.admin')

@section('title', 'آهنگ جدید')
@section('page_title', 'افزودن آهنگ جدید')

@section('header_actions')
    <a href="{{ route('admin.songs.index') }}"
        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        بازگشت به لیست
    </a>
@endsection

@push('styles')
    <style>
        /**
                                                                                 * استایل‌های کمکی آپلودر کاور
                                                                                 */

        [data-cover-uploader] [data-cover-dropzone] {
            -webkit-user-select: none;
            user-select: none;
        }

        /* حالت درگ */
        [data-cover-uploader] [data-cover-dropzone].is-dragging {
            box-shadow: 0 0 0 4px rgb(99 102 241 / 0.15);
        }

        /* شطرنجی پشت عکس‌های PNG شفاف */
        [data-cover-uploader] [data-cover-preview-image] {
            background-color: #fff;
            background-image:
                linear-gradient(45deg, #f1f5f9 25%, transparent 25%),
                linear-gradient(-45deg, #f1f5f9 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #f1f5f9 75%),
                linear-gradient(-45deg, transparent 75%, #f1f5f9 75%);
            background-size: 12px 12px;
            background-position: 0 0, 0 6px, 6px -6px, -6px 0;
        }

        /* انیمیشن ورود پیش‌نمایش */
        [data-cover-uploader] [data-cover-preview]:not(.hidden) {
            animation: cover-fade-in .18s ease-out both;
        }

        @keyframes cover-fade-in {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* فایل اینپوت مخفی ولی قابل فوکوس */
        [data-cover-uploader] input[type="file"]:focus-visible+svg {
            outline: 2px solid #6366f1;
            outline-offset: 4px;
            border-radius: 6px;
        }
    </style>
@endpush

@section('content')

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">

        <form method="POST" action="{{ route('admin.songs.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @include('admin.songs.partials.cover-create')
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- اطلاعات اصلی --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="space-y-5">
                    <h3 class="text-base font-semibold text-gray-900 pb-2 border-b border-gray-100">
                        📝 اطلاعات اصلی
                    </h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">عنوان آهنگ <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}" placeholder="مثلاً: بارون"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('title') ? 'border-red-500' : '' }}">
                        @error('title')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">نام خواننده <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="artist_name" value="{{ old('artist_name') }}"
                            placeholder="مثلاً: سروش هیوه"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('artist_name') ? 'border-red-500' : '' }}">
                        @error('artist_name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">آدرس منبع <span
                                class="text-red-500">*</span></label>
                        <input required type="url" name="source_url" value="{{ old('source_url') }}" dir="ltr"
                            placeholder="https://..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('source_url') ? 'border-red-500' : '' }}">
                        @error('source_url')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">متن آهنگ</label>
                        <textarea name="lyrics" rows="6" placeholder="موقعیت عشق من از پشت نقاب تو..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-none transition">{{ old('lyrics') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">توی سایت نشون بده؟</label>
                        <select
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-none transition"
                            name="show_in_saz_site">

                            <option value="1" {{ old('show_in_saz_site') == 1 ? 'selected' : '' }}>آره</option>
                            <option value="0" {{ old('show_in_saz_site') == 0 ? 'selected' : '' }}>نه</option>

                        </select>
                    </div>


                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">متن معرفی</label>
                        <textarea name="description" rows="6" placeholder="این از اون آهنگ هاست که میره توی پلی لیست..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-none transition">{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">تعداد دانلود</label>
                        <input type="number" name="downloads_count" value="{{ old('downloads_count', 0) }}" min="0"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- اطلاعات فایل و دانلود --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="space-y-5">
                    <h3 class="text-base font-semibold text-gray-900 pb-2 border-b border-gray-100">
                        📦 اطلاعات فایل و دانلود
                    </h3>

                    {{-- لینک‌های دانلود --}}
                    <div class="bg-gray-50 rounded-xl p-4 space-y-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase">لینک‌های دانلود</p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک دانلود ۱۲۸kbps</label>
                            <input type="url" name="download_link_128" value="{{ old('download_link_128') }}" dir="ltr"
                                placeholder="https://..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('download_link_128') ? 'border-red-500' : '' }}">
                            @error('download_link_128')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک دانلود ۳۲۰kbps</label>
                            <input type="url" name="download_link_320" value="{{ old('download_link_320') }}" dir="ltr"
                                placeholder="https://..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('download_link_320') ? 'border-red-500' : '' }}">
                            @error('download_link_320')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- File ID تلگرام --}}
                    <div class="bg-sky-50/40 rounded-xl p-4 space-y-4">
                        <p class="text-xs font-semibold text-sky-700 uppercase">✈️ کش تلگرام (File ID)</p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Telegram File ID (128kbps)</label>
                            <input type="text" name="telegram_file_id_128" value="{{ old('telegram_file_id_128') }}"
                                dir="ltr" placeholder="BQACAgIAAxkBAAI..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Telegram File ID (320kbps)</label>
                            <input type="text" name="telegram_file_id_320" value="{{ old('telegram_file_id_320') }}"
                                dir="ltr" placeholder="BQACAgIAAxkBAAI..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                        </div>
                    </div>

                    {{-- File ID بله --}}
                    <div class="bg-indigo-50/40 rounded-xl p-4 space-y-4">
                        <p class="text-xs font-semibold text-indigo-700 uppercase">🤖 کش بله (File ID)</p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Bale File ID (128kbps)</label>
                            <input type="text" name="bale_file_id_128" value="{{ old('bale_file_id_128') }}" dir="ltr"
                                placeholder="CAACAgIAAxkBAAI..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Bale File ID (320kbps)</label>
                            <input type="text" name="bale_file_id_320" value="{{ old('bale_file_id_320') }}" dir="ltr"
                                placeholder="CAACAgIAAxkBAAI..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                        </div>
                    </div>

                    {{-- آپارات --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک ویدیو آپارات</label>
                        <input type="url" name="aparat_link" value="{{ old('aparat_link') }}" dir="ltr"
                            placeholder="https://www.aparat.com/..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('aparat_link') ? 'border-red-500' : '' }}">
                        @error('aparat_link')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="bg-rose-50/40 rounded-xl p-4 space-y-4">
                    <p class="text-xs font-semibold text-rose-700 uppercase">🎵 منابع جایگزین (در صورت خرابی لینک اصلی)</p>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک یوتیوب</label>
                        <input type="url" name="youtube_link" value="{{ old('youtube_link') }}" dir="ltr"
                            placeholder="https://www.youtube.com/watch?v=..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    </div>

                    {{-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک اسپاتیفای</label>
                        <input type="url" name="spotify_link" value="{{ old('spotify_link') }}" dir="ltr"
                            placeholder="https://open.spotify.com/track/..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    </div> --}}

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">لینک ساندکلاد</label>
                        <input type="url" name="soundcloud_link" value="{{ old('soundcloud_link') }}" dir="ltr"
                            placeholder="https://soundcloud.com/..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    </div>
                </div>
            </div>

            {{-- دکمه‌ها --}}
            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.songs.index') }}"
                    class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                    انصراف
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    ذخیره آهنگ
                </button>
            </div>
        </form>
    </div>

@endsection

@push('scripts')
    <script>
        /**
     * Cover Uploader
     */
        (function () {
            'use strict';

            const HUMAN = (bytes) => {
                if (!bytes && bytes !== 0) return '';
                const units = ['B', 'KB', 'MB', 'GB'];
                let i = 0;
                let n = bytes;
                while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
                return `${n.toFixed(n >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
            };

            function initUploader(root) {
                const input = root.querySelector('[data-cover-input]');
                const dropzone = root.querySelector('[data-cover-dropzone]');
                const preview = root.querySelector('[data-cover-preview]');
                const image = root.querySelector('[data-cover-preview-image]');
                const nameEl = root.querySelector('[data-cover-name]');
                const sizeEl = root.querySelector('[data-cover-size]');
                const removeBtn = root.querySelector('[data-cover-remove]');
                const changeBtn = root.querySelector('[data-cover-change]');
                const errorEl = root.querySelector('[data-cover-error]');

                if (!input || !dropzone) return;

                const maxSizeKb = parseInt(root.dataset.maxSize || '4096', 10);
                const accepted = (root.dataset.accept || 'image/jpeg,image/png,image/webp').split(',').map(s => s.trim());
                const initialUrl = root.dataset.initialImage || '';
                const initialName = root.dataset.initialName || '';
                const hasInitial = initialUrl.length > 0;

                let objectUrl = null;

                const showError = (msg) => {
                    if (!errorEl) return;
                    errorEl.textContent = msg;
                    errorEl.classList.remove('hidden');
                };

                const clearError = () => errorEl && errorEl.classList.add('hidden');

                const revoke = () => {
                    if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
                };

                /** برگشت به حالت اولیه (create: خالی / edit: کاور فعلی) */
                const reset = () => {
                    revoke();
                    input.value = '';
                    clearError();

                    if (hasInitial) {
                        image.src = initialUrl;
                        nameEl.textContent = initialName;
                        sizeEl.textContent = 'کاور فعلی';
                        preview.classList.remove('hidden');
                        dropzone.classList.add('hidden');
                        changeBtn && changeBtn.classList.remove('hidden');
                        removeBtn && removeBtn.classList.add('hidden');
                    } else {
                        image.src = '';
                        nameEl.textContent = '';
                        sizeEl.textContent = '';
                        preview.classList.add('hidden');
                        dropzone.classList.remove('hidden');
                        removeBtn && removeBtn.classList.remove('hidden');
                    }
                };

                const validate = (file) => {
                    if (accepted.length && !accepted.includes(file.type)) {
                        return 'فرمت فایل مجاز نیست. فقط JPG، PNG یا WEBP.';
                    }
                    if (file.size > maxSizeKb * 1024) {
                        return `حجم فایل بیشتر از حد مجاز است (حداکثر ${HUMAN(maxSizeKb * 1024)}).`;
                    }
                    return null;
                };

                const applyFile = (file) => {
                    const err = validate(file);
                    if (err) { showError(err); reset(); return; }

                    clearError();
                    revoke();
                    objectUrl = URL.createObjectURL(file);

                    image.src = objectUrl;
                    nameEl.textContent = file.name;
                    sizeEl.textContent = HUMAN(file.size) + ' — عکس جدید';

                    preview.classList.remove('hidden');
                    dropzone.classList.add('hidden');
                    changeBtn && changeBtn.classList.remove('hidden');
                    removeBtn && removeBtn.classList.remove('hidden');
                };

                input.addEventListener('change', () => {
                    if (input.files && input.files[0]) applyFile(input.files[0]);
                    else reset();
                });

                // drag & drop
                ['dragenter', 'dragover'].forEach(evt =>
                    dropzone.addEventListener(evt, (e) => {
                        e.preventDefault();
                        dropzone.classList.add('is-dragging', 'border-indigo-500', 'bg-indigo-50');
                    })
                );

                ['dragleave', 'drop'].forEach(evt =>
                    dropzone.addEventListener(evt, (e) => {
                        e.preventDefault();
                        dropzone.classList.remove('is-dragging', 'border-indigo-500', 'bg-indigo-50');
                    })
                );

                dropzone.addEventListener('drop', (e) => {
                    const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                    if (!file) return;
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;      // تا با submit فرم ارسال بشه
                    applyFile(file);
                });

                removeBtn && removeBtn.addEventListener('click', reset);

                changeBtn && changeBtn.addEventListener('click', () => {
                    input.click();
                });

                // paste (Ctrl+V) روی همین باکس
                root.addEventListener('paste', (e) => {
                    const item = [...(e.clipboardData?.items || [])].find(i => i.type.startsWith('image/'));
                    if (!item) return;
                    const file = item.getAsFile();
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                    applyFile(file);
                });

                window.addEventListener('beforeunload', revoke);
            }

            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-cover-uploader]').forEach(initUploader);
            });
        })();

    </script>
@endpush