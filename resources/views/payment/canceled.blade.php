@extends('layouts.app')

@section('title', 'پرداخت لغو شد')
@section('body_class', 'flex items-center justify-center px-4')

@section('content')
    <div class="w-full max-w-md text-center">
        <div
            class="mx-auto w-20 h-20 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center mb-6 text-4xl">
            ⚠️</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">پرداخت لغو شد</h1>
        <p class="text-gray-500 mb-8">هروقت خواستی می‌تونی دوباره از ربات اقدام کنی.</p>
        <a href="{{ $payment->bot_deep_link }}"
            class="inline-flex items-center justify-center w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-xl transition">
            برگشت به ربات
        </a>
    </div>
@endsection