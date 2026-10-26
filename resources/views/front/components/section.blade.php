<section class="{{ $class }}{{ filled($attrs['image'] ?? null) ? ' front-section--split' : '' }}"@if (filled($attrs['id'] ?? null)) id="{{ $attrs['id'] }}"@endif data-reveal>
    <div class="front-section__body">
        @if (filled($attrs['kicker'] ?? null))
            <p class="front-kicker">{{ $attrs['kicker'] }}</p>
        @endif
        {!! $html !!}
    </div>
    @if (filled($attrs['image'] ?? null))
        <figure class="front-section__media">
            <img src="{{ $attrs['image'] }}" alt="{{ $attrs['alt'] ?? '' }}"@if (filled($attrs['image_width'] ?? null)) width="{{ $attrs['image_width'] }}" height="{{ $attrs['image_height'] }}"@endif loading="lazy" decoding="async">
        </figure>
    @endif
</section>
