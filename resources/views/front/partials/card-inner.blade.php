@php
    $hasPhoto = filled($attrs['image'] ?? null);
    $button = filled($attrs['button_label'] ?? null) && filled($attrs['href'] ?? null);
    $titleLink = ! $button && filled($attrs['href'] ?? null);
@endphp
<article class="{{ $class }}{{ $hasPhoto ? ' front-card--photo' : '' }}"@if (filled($attrs['id'] ?? null)) id="{{ $attrs['id'] }}"@endif>
    @if ($hasPhoto)
        <img class="front-card__image" src="{{ $attrs['image'] }}" alt="{{ $attrs['alt'] ?? '' }}"@if (filled($attrs['image_width'] ?? null)) width="{{ $attrs['image_width'] }}" height="{{ $attrs['image_height'] }}"@endif loading="lazy" decoding="async">
    @endif
    <div class="front-card__body">
        @include('front.partials.icon', ['attrs' => $attrs, 'size' => 'lg'])
        @include('front.partials.chips', ['chips' => $attrs['chips'] ?? [], 'variant' => $attrs['chip_variant'] ?? null])
        @if (filled($attrs['title'] ?? null))
            <h3 class="front-card__title">
                @if ($titleLink)
                    <a href="{{ $attrs['href'] }}">{{ $attrs['title'] }}</a>
                @else
                    {{ $attrs['title'] }}
                @endif
            </h3>
        @endif
        {!! $html !!}
        @if (filled($attrs['image2'] ?? null))
            <img class="front-card__image front-card__image--second" src="{{ $attrs['image2'] }}" alt="{{ $attrs['alt2'] ?? '' }}"@if (filled($attrs['image2_width'] ?? null)) width="{{ $attrs['image2_width'] }}" height="{{ $attrs['image2_height'] }}"@endif loading="lazy" decoding="async">
        @endif
        @if ($button)
            <p class="front-card__actions"><a class="btn btn-primary" href="{{ $attrs['href'] }}">{{ $attrs['button_label'] }}</a></p>
        @endif
    </div>
</article>
