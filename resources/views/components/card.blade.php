@props([
    'title' => null,
    'kicker' => null,
])

<div {{ $attributes->class('border-2 border-line bg-surface') }}>
    @if ($title || $kicker || isset($actions))
        <div class="flex items-center justify-between gap-3 border-b-2 border-line px-5 py-3">
            <div>
                @if ($kicker)
                    <p class="text-kicker uppercase text-accent">{{ $kicker }}</p>
                @endif
                @if ($title)
                    <h2 class="text-card-title text-ink">{{ $title }}</h2>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center justify-start gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="p-5">{{ $slot }}</div>
</div>
