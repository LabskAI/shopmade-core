<div class="{{ $class }}"@if (filled($attrs['id'] ?? null)) id="{{ $attrs['id'] }}"@endif data-reveal>
    @if (trim($html) !== '')
        <div class="front-cards__intro">{!! $html !!}</div>
    @endif
    <div class="front-cards__grid">
        @foreach ($items as $item)
            @include('front.partials.card-inner', $item)
        @endforeach
    </div>
</div>
