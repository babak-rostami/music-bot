@extends('layouts.app')

@section('title', 'پرداخت موفق')
@section('body_class', 'flex items-center justify-center px-4')

@section('content')
    <div class="w-full max-w-md text-center">
        <div
            class="mx-auto w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-6 text-4xl">
            ✅</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">پرداخت با موفقیت انجام شد</h1>
        <p class="text-gray-500 mb-1">پلن {{ $payment->subscription?->plan_name_snapshot }} فعال شد.</p>
        <p class="text-gray-500 mb-8">اعتبار تا:
            {{ $payment->subscription ? jdate($payment->subscription->expires_at)->format('Y/m/d') : '-' }}</p>
        <a href="{{ $payment->bot_deep_link }}"
            class="inline-flex items-center justify-center w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition">
            برگشت به ربات 🚀
        </a>
    </div>
@endsection