<section class="{{ $class }}"@if (filled($attrs['id'] ?? null)) id="{{ $attrs['id'] }}"@endif data-reveal>
    {!! $html !!}
    @if ($items !== [])
        <ul class="front-proof__points">
            @foreach ($items as $item)
                <li class="front-proof__point">
                    @include('front.partials.icon', ['attrs' => $item['attrs'], 'size' => 'md'])
                    <div class="front-proof__text">
                        @if (filled($item['attrs']['title'] ?? null))
                            <h3 class="front-proof__title">{{ $item['attrs']['title'] }}</h3>
                        @endif
                        {!! $item['html'] !!}
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    @if (filled($attrs['href'] ?? null))
        <p class="front-proof__more"><a href="{{ $attrs['href'] }}">{{ $attrs['label'] ?? $attrs['href'] }}</a></p>
    @endif
</section>
