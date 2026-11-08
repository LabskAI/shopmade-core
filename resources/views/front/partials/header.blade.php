@php
    $hasMenu = count($navigation) > 1 || count($languageLinks) > 1;
@endphp
<header class="site-header bs">
    <div class="wrap f fw ac g4 py3">
        <a class="brand x t3 b nu" href="{{ $homeUrl }}">
            @if ($logo)
                <img class="brand-logo" src="{{ $logo['src'] }}" alt="{{ $logo['alt'] }}"@if ($logo['width']) width="{{ $logo['width'] }}" height="{{ $logo['height'] }}"@endif>
            @else
                {{ $name }}
            @endif
        </a>

        @if ($hasMenu)
            {{-- Phones get a popover menu (light-dismiss, Esc, aria-expanded from the browser); 768px and up show it inline. --}}
            <div class="site-menu g4" id="site-menu" popover>
                @if (count($navigation) > 1)
                    <nav class="site-nav" aria-label="{{ $t['menu'] }}">
                        <ul class="f fc g3 m-fr m-fw m-g4">
                            @foreach ($navigation as $item)
                                <li><a href="{{ $item['url'] }}"@if ($item['current']) aria-current="page"@endif>{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                @if (count($languageLinks) > 1)
                    <nav class="lang-switch t1" aria-label="{{ $t['languages'] }}">
                        <ul class="f fw g4">
                            @foreach ($languageLinks as $link)
                                <li><a href="{{ $link['url'] }}" hreflang="{{ $link['lang'] }}" lang="{{ $link['lang'] }}"@if ($link['current']) aria-current="true"@endif>{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </div>
        @endif

        @if ($cta)
            <a class="btn btn-primary header-cta" href="{{ $cta['href'] }}">{{ $cta['label'] }}</a>
        @elseif ($phone)
            <a class="call" href="tel:{{ $phoneHref }}">{{ $t['call'] }} {{ $phone }}</a>
        @endif

        @if ($hasMenu)
            <button type="button" class="menu-toggle m-hd r bs2 ci px4 py2 sb cp" popovertarget="site-menu">{{ $t['menu_button'] }}</button>
        @endif
    </div>
</header>
