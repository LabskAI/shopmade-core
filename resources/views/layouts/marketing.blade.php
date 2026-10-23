<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @hasSection('canonical')
    <link rel="canonical" href="@yield('canonical')">
    @endif
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://api.fontshare.com/css?f%5B%5D=switzer@400,500,600,700&amp;display=swap">
    @vite(['resources/css/auth.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-gray-950 antialiased">
    @yield('content')
</body>
</html>
