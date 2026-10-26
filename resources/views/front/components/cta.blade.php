<aside class="{{ $class }}"@if (filled($attrs['id'] ?? null)) id="{{ $attrs['id'] }}"@endif data-reveal>
    @if (filled($attrs['image'] ?? null))
        <img class="front-cta__image" src="{{ $attrs['image'] }}" alt=""@if (filled($attrs['image_width'] ?? null)) width="{{ $attrs['image_width'] }}" height="{{ $attrs['image_height'] }}"@endif loading="lazy" decoding="async">
    @endif
    <div class="front-cta__body">
        @if (filled($attrs['title'] ?? null))
            <h2 class="front-cta__title">{{ $attrs['title'] }}</h2>
        @endif
        {!! $html !!}
        @if (filled($attrs['href'] ?? null) || filled($attrs['secondary_href'] ?? null))
            <p class="front-cta__actions">
                @if (filled($attrs['href'] ?? null))
                    <a class="btn btn-primary" href="{{ $attrs['href'] }}">{{ $attrs['label'] ?? $attrs['title'] ?? $attrs['href'] }}</a>
                @endif
                @if (filled($attrs['secondary_href'] ?? null))
                    <a class="btn btn-outline" href="{{ $attrs['secondary_href'] }}">{{ $attrs['secondary_label'] ?? $attrs['secondary_href'] }}</a>
                @endif
            </p>
        @endif
    </div>
</aside>
