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
    <meta name="color-scheme" content="{{ $colorScheme }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $name }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ $lang }}">
    @if ($shareImage)
        <meta property="og:image" content="{{ $shareImage }}">
    @endif
    <meta name="twitter:card" content="{{ $shareImage ? 'summary_large_image' : 'summary' }}">
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
    <style>{!! \App\Core\Front\FrontCss::core() !!}{!! \App\Core\Front\FrontCss::roles() !!}{!! $tokens !!}</style>
    <style>{!! \App\Core\Front\FrontCss::kit() !!}</style>
    {!! \App\Core\Front\FrontCss::PLACEHOLDER !!}
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
    <main id="main" class="py7">
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
