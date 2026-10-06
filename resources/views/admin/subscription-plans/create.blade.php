@extends('layouts.admin')

@section('title', 'پلن جدید')
@section('page_title', 'ساخت پلن اشتراک جدید')

@section('content')
    @if ($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 max-w-xl">
        <form method="POST" action="{{ route('admin.subscription-plans.store') }}" class="space-y-5">
            @csrf
            @include('admin.subscription-plans.form-fields')
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.subscription-plans.index') }}"
                    class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">انصراف</a>
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">ذخیره</button>
            </div>
        </form>
    </div>
@endsection