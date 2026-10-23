@props([
    'label',
    'value',
    'delta' => null,
    'direction' => 'flat',
    'tone' => 'neutral',
])

@php
    $toneClass = match ($tone) {
        'ok' => 'text-ok-700',
        'bad' => 'text-accent-700',
        default => 'text-ink/55',
    };

    $icon = match ($direction) {
        'up' => 'arrow-up',
        'down' => 'arrow-down',
        default => 'minus',
    };
@endphp

<div {{ $attributes->class('bg-surface p-5') }}>
    <p class="text-label text-ink/70">{{ $label }}</p>
    <p class="text-stat tabular-nums text-ink">{{ $value }}</p>
    @if ($delta)
        <p class="mt-1 inline-flex items-center gap-1 text-label {{ $toneClass }}">
            <i data-lucide="{{ $icon }}" class="size-4"></i>
            <span>{{ $delta }}</span>
        </p>
    @endif
</div>
