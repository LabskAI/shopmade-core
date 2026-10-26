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
    @isset($icons['favicon.svg'])
        <link rel="icon" href="{{ $icons['favicon.svg'] }}" type="image/svg+xml">
    @endisset
    @isset($icons['favicon.ico'])
        <link rel="icon" href="{{ $icons['favicon.ico'] }}" sizes="any">
    @endisset
    @isset($icons['apple-touch-icon.png'])
        <link rel="apple-touch-icon" href="{{ $icons['apple-touch-icon.png'] }}">
    @endisset
    @isset($icons['site.webmanifest'])
        <link rel="manifest" href="{{ $icons['site.webmanifest'] }}">
    @endisset
    @if ($hero['image'] ?? null)
        <link rel="preload" as="image" href="{{ $hero['image'] }}" fetchpriority="high">
    @endif
    @include('front.partials.fonts')
    <style>{!! $tokens !!}</style>
    @include('front.partials.styles')
    @if (filled($componentsCss))
        <style>{!! $componentsCss !!}</style>
    @endif
    @foreach ($jsonLd as $block)
        <script type="application/ld+json"@if ($cspNonce) nonce="{{ $cspNonce }}"@endif>{!! $block !!}</script>
    @endforeach
</head>
<body class="layout-{{ $layout }}">
    <a class="skip" href="#main">{{ $t['skip_to_content'] }}</a>
    @include('front.partials.header')
    <main id="main">
        @yield('content')
    </main>
    @include('front.partials.footer')
    @if ($embedScript)
        <script src="{{ $embedScript }}" defer @if ($cspNonce) nonce="{{ $cspNonce }}"@endif></script>
    @endif
    @foreach ($scripts as $script)
        <script src="{{ $script['src'] }}"@if ($script['module']) type="module"@elseif ($script['defer']) defer @endif @if ($cspNonce) nonce="{{ $cspNonce }}"@endif></script>
    @endforeach
</body>
</html>
