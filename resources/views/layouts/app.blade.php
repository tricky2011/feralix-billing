<!DOCTYPE html>
<html lang="id" class="h-full bg-[#f4efe4]">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', $title ?? 'Feralix Billing')</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans text-slate-950 antialiased">
        @yield('content')
    </body>
</html>
