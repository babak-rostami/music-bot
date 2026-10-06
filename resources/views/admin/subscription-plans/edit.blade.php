@extends('layouts.admin')

@section('title', 'ویرایش پلن')
@section('page_title', 'ویرایش پلن: ' . $plan->name)

@section('content')
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 max-w-xl">
        <form method="POST" action="{{ route('admin.subscription-plans.update', $plan) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('admin.subscription-plans.form-fields')
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.subscription-plans.index') }}"
                    class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">انصراف</a>
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">ذخیره
                    تغییرات</button>
            </div>
        </form>
    </div>
@endsection