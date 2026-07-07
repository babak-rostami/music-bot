@extends('layouts.admin')

@section('title', 'ویرایش کاربر')
@section('page_title', 'ویرایش کاربر: ' . Illuminate\Support\Str::limit($user->username, 30))

@section('header_actions')
    <a href="{{ route('admin.users.index') }}"
        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        بازگشت به لیست
    </a>
@endsection

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- فرم ویرایش اطلاعات --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-5">📝 اطلاعات کاربر</h3>

            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">نام کاربری <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="username" value="{{ old('username', $user->username) }}"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('username') ? 'border-red-500' : '' }}">
                        @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">نام کامل</label>
                        <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">ایمیل <span
                                class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" dir="ltr"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('email') ? 'border-red-500' : '' }}">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">شماره تلفن</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" dir="ltr"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">نقش <span
                                class="text-red-500">*</span></label>
                        <select name="role"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white transition">
                            <option value="admin" @selected(old('role', $user->role) === 'admin')>ادمین</option>
                            <option value="user" @selected(old('role', $user->role) === 'user')>کاربر</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">وضعیت</label>
                        <label class="flex items-center gap-2 mt-2.5 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">حساب فعال است</span>
                        </label>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">بیوگرافی</label>
                        <textarea name="bio" rows="3"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-none transition">{{ old('bio', $user->bio) }}</textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- تغییر رمز عبور --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-2">🔑 تغییر رمز عبور</h3>
            <p class="text-xs text-gray-500 mb-5">رمز عبور جدید برای این کاربر تنظیم کنید.</p>

            <form method="POST" action="{{ route('admin.users.change-password', $user) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">رمز عبور جدید <span
                                class="text-red-500">*</span></label>
                        <input type="password" name="password"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition {{ $errors->has('password') ? 'border-red-500' : '' }}">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">تکرار رمز عبور <span
                                class="text-red-500">*</span></label>
                        <input type="password" name="password_confirmation"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-yellow-500 text-white text-sm font-medium rounded-xl hover:bg-yellow-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        تغییر رمز عبور
                    </button>
                </div>
            </form>

            {{-- اطلاعات اضافی --}}
            <div class="mt-6 pt-6 border-t border-gray-100 space-y-3">
                <div>
                    <p class="text-xs text-gray-500">تاریخ عضویت</p>
                    <p class="text-sm font-medium text-gray-900">{{ jdate($user->created_at)->format('Y/m/d H:i') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">آخرین بروزرسانی</p>
                    <p class="text-sm font-medium text-gray-900">{{ jdate($user->updated_at)->format('Y/m/d H:i') }}</p>
                </div>
            </div>
        </div>
    </div>

@endsection