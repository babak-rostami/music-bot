@extends('layouts.app')

@section('title', 'ورود ادمین')

@section('body_class', 'flex items-center justify-center px-4')

@section('content')
    <div class="w-full max-w-md">
        <!-- لوگو / عنوان -->
        <div class="text-center mb-8">
            <div class="mx-auto w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">ورود به پنل مدیریت</h1>
            <p class="text-gray-500 mt-1">لطفاً اطلاعات خود را وارد کنید</p>
        </div>

        <!-- فرم لاگین -->
        <div class="bg-white rounded-2xl shadow-lg p-8">
            @if(session('status'))
                <div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.send') }}">
                @csrf

                <!-- ایمیل -->
                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">ایمیل</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                        placeholder="admin@example.com"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 placeholder-gray-400
                               {{ $errors->has('email') ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : '' }}">
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- رمز عبور -->
                <div class="mb-5">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">رمز عبور</label>
                    <input type="password" id="password" name="password" required placeholder="رمز عبور خود را وارد کنید"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 placeholder-gray-400
                               {{ $errors->has('password') ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : '' }}">
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- مرا به خاطر بسپار -->
                <div class="mb-6 flex items-center gap-2">
                    <input type="checkbox" id="remember" name="remember"
                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="remember" class="text-sm text-gray-600">مرا به خاطر بسپار</label>
                </div>

                <!-- دکمه ورود -->
                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    ورود
                </button>
            </form>
        </div>

        <p class="text-center text-gray-400 text-sm mt-6">
            پنل مدیریت &copy; {{ date('Y') }}
        </p>
    </div>
@endsection