@extends('layouts.admin')

@section('title', 'پرداخت‌ها')
@section('page_title', 'مدیریت پرداخت‌ها')

@section('content')

    @if (session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">✅
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">❌ {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">مجموع درآمد</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($stats['total_revenue']) }} تومان</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">درآمد امروز</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['today_revenue']) }} تومان</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">پرداخت‌های موفق</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['paid_count']) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">در انتظار / رهاشده</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1">{{ number_format($stats['pending_count']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-wrap gap-4 items-end">
            <div class="min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">پلتفرم</label>
                <select name="platform" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm bg-white">
                    <option value="">همه</option>
                    <option value="telegram" @selected($platform === 'telegram')>تلگرام</option>
                    <option value="bale" @selected($platform === 'bale')>بله</option>
                </select>
            </div>
            <div class="min-w-[180px]">
                <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
                <select name="status" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm bg-white">
                    <option value="">همه</option>
                    <option value="created" @selected($status === 'created')>ساخته‌شده (کلیک نشده)</option>
                    <option value="pending" @selected($status === 'pending')>در انتظار (رهاشده؟)</option>
                    <option value="paid" @selected($status === 'paid')>پرداخت‌شده</option>
                    <option value="failed" @selected($status === 'failed')>ناموفق</option>
                    <option value="expired" @selected($status === 'expired')>منقضی‌شده</option>
                </select>
            </div>
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">شناسه کاربر</label>
                <input type="text" name="user_id" value="{{ $userId }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm">
            </div>
            <button type="submit"
                class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">🔍
                اعمال</button>
            <a href="{{ route('admin.payments.index') }}"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">پاک کردن</a>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">کاربر</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">پلن</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">مبلغ</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">وضعیت</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">ref_id</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">تاریخ</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-5">
                                @if($payment->botUser)
                                    <a href="{{ route('admin.bot-users.show', $payment->botUser->id) }}"
                                        class="text-indigo-600 hover:underline">
                                        {{ $payment->botUser->full_name }}
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                                <p class="text-xs text-gray-400 font-mono">{{ $payment->user_id }}
                                    ({{ $payment->platform === 'bale' ? 'بله' : 'تلگرام' }})</p>
                            </td>
                            <td class="py-3 px-5 text-gray-700">
                                @if($payment->payable instanceof \App\Models\SubscriptionPurchase)
                                    {{ $payment->payable->plan_name_snapshot }} ({{ $payment->payable->duration_months }} ماهه)
                                @else
                                    {{ class_basename($payment->payable_type) }}
                                @endif
                            </td>
                            <td class="py-3 px-5 text-center text-gray-700 whitespace-nowrap">
                                {{ number_format($payment->amount_toman) }} ت
                            </td>
                            <td class="py-3 px-5 text-center">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $payment->status->badgeClasses() }}">
                                    {{ $payment->status->label() }}
                                </span>
                            </td>
                            <td class="py-3 px-5 text-gray-500 font-mono text-xs">{{ $payment->ref_id ?? '—' }}</td>
                            <td class="py-3 px-5 text-gray-500 whitespace-nowrap">{{ jdate($payment->created_at)->ago() }}</td>
                            <td class="py-3 px-5 text-center">
                                @if(in_array($payment->status, [\App\Enums\PaymentStatus::Pending, \App\Enums\PaymentStatus::Created], true) && $payment->authority)
                                    <form method="POST" action="{{ route('admin.payments.verify-now', $payment) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="px-3 py-1.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-100">
                                            بررسی الان
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-400">پرداختی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
            <div class="px-5 py-4 border-t border-gray-200">{{ $payments->links() }}</div>
        @endif
    </div>

@endsection