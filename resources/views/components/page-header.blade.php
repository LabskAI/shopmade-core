@props([
    'title',
    'kicker' => null,
])

<div {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-4 border-b-2 border-line pb-4') }}>
    <div>
        @if ($kicker)
            <p class="text-kicker uppercase text-accent">{{ $kicker }}</p>
        @endif
        <h1 class="text-title text-ink">{{ $title }}</h1>
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center justify-start gap-2">{{ $actions }}</div>
    @endisset
</div>
