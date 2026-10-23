@props([
    'title' => null,
    'count' => null,
])

<div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 border-b-2 border-line bg-surface px-4 py-3') }}>
    <div class="flex flex-wrap items-center justify-start gap-3">
        @if ($title)
            <p class="text-card-title text-ink">{{ $title }}</p>
        @endif
        @if ($count !== null)
            <p class="text-label tabular-nums text-ink/55">{{ $count }}</p>
        @endif
        {{ $left ?? '' }}
    </div>
    <div class="flex flex-wrap items-center justify-start gap-2">
        {{ $right ?? '' }}
    </div>
</div>
