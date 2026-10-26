@php
    $stats = array_values(array_filter($items, fn (array $item): bool => $item['name'] === 'stat'));
    $steps = array_values(array_filter($items, fn (array $item): bool => $item['name'] === 'step'));
@endphp
<article class="{{ $class }}"@if (filled($attrs['id'] ?? null)) id="{{ $attrs['id'] }}"@endif data-reveal>
    @if (filled($attrs['title'] ?? null) || filled($attrs['where'] ?? null) || filled($attrs['span'] ?? null))
        <header class="front-timeline__head">
            @if (filled($attrs['title'] ?? null))
                <h3 class="front-timeline__title">{{ $attrs['title'] }}</h3>
            @endif
            @if (filled($attrs['where'] ?? null))
                <p class="front-timeline__where">{{ $attrs['where'] }}</p>
            @endif
            @if (filled($attrs['span'] ?? null))
                <p class="front-timeline__span">{{ $attrs['span'] }}</p>
            @endif
        </header>
    @endif
    @if ($stats !== [])
        <dl class="front-timeline__stats">
            @foreach ($stats as $stat)
                <div class="{{ $stat['class'] }}">
                    <dt class="front-stat__label">{{ $stat['attrs']['label'] ?? '' }}</dt>
                    <dd class="front-stat__value">{{ $stat['attrs']['value'] ?? '' }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
    @if ($steps !== [])
        <ol class="front-timeline__list">
            @foreach ($steps as $step)
                <li class="{{ $step['class'] }} front-timeline__step">
                    @if (filled($step['attrs']['time'] ?? null))
                        <p class="front-timeline__time">{{ $step['attrs']['time'] }}</p>
                    @endif
                    <div class="front-timeline__what">
                        @if (filled($step['attrs']['title'] ?? null))
                            <p class="front-timeline__event">{{ $step['attrs']['title'] }}</p>
                        @endif
                        {!! $step['html'] !!}
                    </div>
                    @if (filled($step['attrs']['status'] ?? null))
                        <span class="front-chip front-timeline__status">{{ $step['attrs']['status'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
    {!! $html !!}
    @if (filled($attrs['note'] ?? null))
        <p class="front-timeline__note">{{ $attrs['note'] }}</p>
    @endif
</article>
