<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'پنل مدیریت')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('styles')
</head>

<body class="min-h-screen bg-gray-100 @yield('body_class', '')">

    @section('content')
    {{-- محتوای هر صفحه اینجا قرار می‌گیره --}}
    @show

    @stack('scripts')
</body>

</html>