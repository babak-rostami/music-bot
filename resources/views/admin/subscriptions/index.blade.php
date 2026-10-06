@extends('layouts.admin')

@section('title', 'اشتراک‌ها')
@section('page_title', 'اشتراک‌های کاربران')

@section('content')

    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">اشتراک‌های فعال</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($stats['active_count']) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500">منقضی‌شده</p>
            <p class="text-2xl font-bold text-gray-500 mt-1">{{ number_format($stats['expired_count']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="flex flex-wrap gap-4 items-end">
            <div class="min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">پلتفرم</label>
                <select name="platform" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm bg-white">
                    <option value="">همه</option>
                    <option value="telegram" @selected($platform === 'telegram')>تلگرام</option>
                    <option value="bale" @selected($platform === 'bale')>بله</option>
                </select>
            </div>
            <div class="min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
                <select name="status" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm bg-white">
                    <option value="">همه</option>
                    <option value="active" @selected($status === 'active')>فعال</option>
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
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">کاربر</th>
                        <th class="text-right py-3 px-5 text-gray-500 font-medium">پلن</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">محدودیت</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">مدت</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">شروع</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">پایان</th>
                        <th class="text-center py-3 px-5 text-gray-500 font-medium">وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $sub)
                        @php $botUser = $botUsers->get($sub->platform . ':' . $sub->user_id); @endphp
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-5">
                                @if($botUser)
                                    <a href="{{ route('admin.bot-users.show', $botUser->id) }}"
                                        class="text-indigo-600 hover:underline">{{ $botUser->full_name }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                                <p class="text-xs text-gray-400 font-mono">{{ $sub->user_id }}
                                    ({{ $sub->platform === 'bale' ? 'بله' : 'تلگرام' }})</p>
                            </td>
                            <td class="py-3 px-5 text-gray-700">{{ $sub->plan_name_snapshot }}</td>
                            <td class="py-3 px-5 text-center text-gray-600">{{ $sub->requests_per_window_snapshot }} /
                                {{ $sub->window_hours_snapshot }}س</td>
                            <td class="py-3 px-5 text-center text-gray-600">{{ $sub->duration_months }} ماهه</td>
                            <td class="py-3 px-5 text-center text-gray-500">{{ jdate($sub->starts_at)->format('Y/m/d') }}</td>
                            <td class="py-3 px-5 text-center text-gray-500">{{ jdate($sub->expires_at)->format('Y/m/d') }}</td>
                            <td class="py-3 px-5 text-center">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sub->isCurrentlyActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $sub->isCurrentlyActive() ? 'فعال' : $sub->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-400">اشتراکی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($subscriptions->hasPages())
            <div class="px-5 py-4 border-t border-gray-200">{{ $subscriptions->links() }}</div>
        @endif
    </div>

@endsection