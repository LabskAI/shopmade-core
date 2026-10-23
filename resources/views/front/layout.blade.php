<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @if ($noindex)
        <meta name="robots" content="noindex">
    @endif
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($alternates as $alternate)
        <link rel="alternate" hreflang="{{ $alternate['lang'] }}" href="{{ $alternate['url'] }}">
    @endforeach
    <link rel="alternate" type="text/markdown" href="{{ $canonical }}">
    <style>{!! $tokens !!}</style>
    @include('front.partials.styles')
    @foreach ($jsonLd as $block)
        <script type="application/ld+json">{!! $block !!}</script>
    @endforeach
</head>
<body class="layout-{{ $layout }}">
    <a class="skip" href="#main">{{ $t['skip_to_content'] }}</a>
    @include('front.partials.header')
    <main id="main">
        @yield('content')
    </main>
    @include('front.partials.footer')
</body>
</html>
