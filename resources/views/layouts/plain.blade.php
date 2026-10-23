<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @include('partials.favicons')
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f5f5f4; color: #1c1917; }
        main { max-width: 32rem; margin: 4rem auto; padding: 0 1.5rem; }
        h1 { font-size: 1.25rem; font-weight: 600; }
        p { line-height: 1.5; color: #44403c; }
    </style>
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>
