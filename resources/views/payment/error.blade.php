@extends('layouts.app')

@section('title', 'خطا در پرداخت')
@section('body_class', 'flex items-center justify-center px-4')

@section('content')
    <div class="w-full max-w-md text-center">
        <div class="mx-auto w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center mb-6 text-4xl">❌
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">مشکلی پیش اومد</h1>
        <p class="text-gray-500 mb-8">اگه مبلغی از حسابت کم شده، خودکار طی چند دقیقه بررسی و اصلاح می‌شه. اگه نگرانی، به
            پشتیبانی پیام بده.</p>
        <a href="{{ $payment->bot_deep_link }}"
            class="inline-flex items-center justify-center w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-xl transition">
            برگشت به ربات
        </a>
    </div>
@endsection