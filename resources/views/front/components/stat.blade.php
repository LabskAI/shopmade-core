<div class="{{ $class }}">
    @if (filled($attrs['value'] ?? null))
        <p class="front-stat__value">{{ $attrs['value'] }}</p>
    @endif
    @if (filled($attrs['label'] ?? null))
        <p class="front-stat__label">{{ $attrs['label'] }}</p>
    @endif
    {!! $html !!}
</div>
