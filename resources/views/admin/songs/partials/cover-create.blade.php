{{-- ============================================================
بخش انتخاب عکس — حالت «ایجاد آهنگ»
این تکه را داخل فرم create خودت (بین <form> ... </form>) بگذار.
🔴 فرم حتماً باید enctype="multipart/form-data" داشته باشد.
============================================================ --}}

<div class="w-full" data-cover-uploader data-max-size="4096" {{-- حداکثر حجم به کیلوبایت --}}
    data-accept="image/jpeg,image/png,image/webp">
    <label for="cover" class="block mb-2 text-sm font-medium text-gray-700">
        کاور آهنگ
        <span class="text-gray-400 font-normal">(اختیاری)</span>
    </label>

    {{-- اینپوت بیرون از dropzone تا هنگام مخفی‌شدن dropzone غیرقابل‌کلیک نشود --}}
    <input type="file" name="cover" id="cover" data-cover-input accept="image/jpeg,image/png,image/webp"
        class="sr-only">

    {{-- ناحیه‌ی دراپ / انتخاب فایل --}}
    <div data-cover-dropzone role="button" tabindex="0"
        class="flex flex-col items-center justify-center w-full rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center transition-colors duration-200 cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/40">
        <svg class="mb-3 h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 7.5 12 3m0 0L7.5 7.5M12 3v13.5" />
        </svg>

        <p class="text-sm font-medium text-gray-700">
            عکس را بکش و اینجا رها کن یا کلیک کن
        </p>
        <p class="mt-1 text-xs text-gray-500">
            JPG یا PNG یا WEBP — حداکثر ۴ مگابایت
        </p>
    </div>

    {{-- پیش‌نمایش --}}
    <div data-cover-preview class="mt-4 hidden">
        <div class="relative inline-flex items-start gap-4 rounded-2xl border border-gray-200 bg-white p-3 shadow-sm">
            <img data-cover-preview-image src="" alt="پیش‌نمایش کاور"
                class="h-28 w-28 rounded-xl object-cover ring-1 ring-gray-200">

            <div class="flex flex-col gap-1 pt-1 pe-2">
                <span data-cover-name class="max-w-[16rem] truncate text-sm font-medium text-gray-800"></span>
                <span data-cover-size class="text-xs text-gray-500"></span>

                <div class="mt-2 flex items-center gap-2">
                    <button type="button" data-cover-change
                        class="inline-flex w-fit items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-600 transition hover:bg-indigo-100">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                        </svg>
                        تغییر عکس
                    </button>

                    <button type="button" data-cover-remove
                        class="inline-flex w-fit items-center gap-1 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        حذف عکس
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- خطای سمت کلاینت --}}
    <p data-cover-error class="mt-2 hidden text-sm text-red-600"></p>

    {{-- خطای سمت سرور --}}
    @error('cover')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>