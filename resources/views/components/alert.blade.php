@props([
    'tone' => 'info',
    'title' => null,
])

@php
    $classes = match ($tone) {
        'ok' => 'border-l-2 border-ok-600 bg-ok-100',
        'warn' => 'border-l-2 border-warn-600 bg-warn-100',
        'bad' => 'border-l-2 border-accent bg-accent-100',
        default => 'border-l-2 border-info-600 bg-info-100',
    };

    $titleClass = match ($tone) {
        'ok' => 'text-ok-800',
        'warn' => 'text-warn-800',
        'bad' => 'text-accent-800',
        default => 'text-info-800',
    };
@endphp

<div {{ $attributes->class(['px-4 py-3', $classes]) }}>
    @if ($title)
        <p class="text-body font-extrabold {{ $titleClass }}">{{ $title }}</p>
    @endif
    <div class="text-body text-ink">{{ $slot }}</div>
</div>
