@php $plan = $plan ?? null; @endphp

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">نام پلن</label>
        <input type="text" name="name" value="{{ old('name', $plan?->name) }}" placeholder="برنزی"
            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">ایموجی</label>
        <input type="text" name="emoji" value="{{ old('emoji', $plan?->emoji) }}" placeholder="🥉"
            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
    </div>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">تعداد درخواست</label>
        <input type="number" name="requests_per_window"
            value="{{ old('requests_per_window', $plan?->requests_per_window) }}"
            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">بازه (ساعت)</label>
        <input type="number" name="window_hours" value="{{ old('window_hours', $plan?->window_hours ?? 12) }}"
            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
    </div>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">قیمت پایه ماهانه (تومان)</label>
        <input type="number" name="monthly_price_toman"
            value="{{ old('monthly_price_toman', $plan?->monthly_price_toman) }}"
            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1.5">ترتیب نمایش</label>
    <input type="number" name="sort_order" value="{{ old('sort_order', $plan?->sort_order ?? 0) }}"
        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
</div>

<label class="flex items-center gap-2">
    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $plan?->is_active ?? true) ? 'checked' : '' }}
        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
    <span class="text-sm text-gray-700">پلن فعال باشه (توی /upgrade نشون داده بشه)</span>
</label>