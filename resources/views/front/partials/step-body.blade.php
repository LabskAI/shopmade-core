@include('front.partials.icon', ['attrs' => $attrs, 'size' => 'lg'])
@if (filled($attrs['image'] ?? null))
    <img class="front-step__image" src="{{ $attrs['image'] }}" alt="{{ $attrs['alt'] ?? '' }}"@if (filled($attrs['image_width'] ?? null)) width="{{ $attrs['image_width'] }}" height="{{ $attrs['image_height'] }}"@endif loading="lazy" decoding="async">
@endif
@if (filled($attrs['time'] ?? null))
    <p class="front-step__time">{{ $attrs['time'] }}</p>
@endif
@if (filled($attrs['title'] ?? null))
    <h3 class="front-step__title">{{ $attrs['title'] }}</h3>
@endif
{!! $html !!}
@if (filled($attrs['chip'] ?? null))
    <p class="front-chip front-step__chip">{{ $attrs['chip'] }}</p>
@endif
