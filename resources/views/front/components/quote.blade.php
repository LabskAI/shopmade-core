<figure class="{{ $class }}">
    <blockquote class="front-quote__text">{!! $html !!}</blockquote>
    @if (filled($attrs['author'] ?? null) || filled($attrs['role'] ?? null))
        <figcaption class="front-quote__by">
            @if (filled($attrs['author'] ?? null))<cite>{{ $attrs['author'] }}</cite>@endif
            @if (filled($attrs['role'] ?? null))<span>{{ $attrs['role'] }}</span>@endif
        </figcaption>
    @endif
</figure>
