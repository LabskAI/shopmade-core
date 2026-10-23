<header class="site-header">
    <div class="wrap header-row">
        <a class="brand" href="{{ $homeUrl }}">{{ $name }}</a>

        @if (count($navigation) > 1)
            <nav class="site-nav" aria-label="{{ $t['menu'] }}">
                <ul>
                    @foreach ($navigation as $item)
                        <li><a href="{{ $item['url'] }}"@if ($item['current']) aria-current="page"@endif>{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if (count($languageLinks) > 1)
            <nav class="lang-switch" aria-label="{{ $t['languages'] }}">
                <ul>
                    @foreach ($languageLinks as $link)
                        <li><a href="{{ $link['url'] }}" hreflang="{{ $link['lang'] }}" lang="{{ $link['lang'] }}"@if ($link['current']) aria-current="true"@endif>{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($phone)
            <a class="call" href="tel:{{ $phoneHref }}">{{ $t['call'] }} {{ $phone }}</a>
        @endif
    </div>
</header>
