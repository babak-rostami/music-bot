@extends('layouts.admin')

@section('title', 'پلن‌های اشتراک')
@section('page_title', 'مدیریت پلن‌های اشتراک')

@section('header_actions')
    <a href="{{ route('admin.subscription-plans.create') }}"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        پلن جدید
    </a>
@endsection

@section('content')

    @if (session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">پلن</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">محدودیت</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">قیمت</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">تعداد فروش</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">وضعیت</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-5 font-medium text-gray-900">{{ $plan->display_title }}</td>
                            <td class="py-3 px-5 text-center text-gray-600">{{ $plan->requests_per_window }} /
                                {{ $plan->window_hours }} ساعت
                            </td>
                            <td class="py-3 px-5 text-center text-gray-600">{{ number_format($plan->monthly_price_toman) }} ت /
                                ماه</td>
                            <td class="py-3 px-5 text-center text-gray-600">{{ number_format($plan->subscriptions_count) }}</td>
                            <td class="py-3 px-5 text-center">
                                <form method="POST" action="{{ route('admin.subscription-plans.toggle-active', $plan) }}"
                                    class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $plan->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $plan->is_active ? 'فعال' : 'غیرفعال' }}
                                    </button>
                                </form>
                            </td>
                            <td class="py-3 px-5">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.subscription-plans.edit', $plan) }}"
                                        class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">✏️</a>
                                    <form method="POST" action="{{ route('admin.subscription-plans.destroy', $plan) }}"
                                        onsubmit="return confirm('حذف بشه؟ پرداخت‌های قبلی این پلن دست‌نخورده می‌مونن.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-400">هنوز پلنی ساخته نشده.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection