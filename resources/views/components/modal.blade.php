@props([
    'name',
    'title' => null,
])

<div
    {{ $attributes->class('relative') }}
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-40 bg-ink/40"
        @click="open = false"
    ></div>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-x-4 top-1/2 z-50 mx-auto w-full max-w-lg -translate-y-1/2 border-2 border-line bg-surface shadow-lg"
    >
        @if ($title)
            <div class="border-b-2 border-line px-5 py-3">
                <h2 class="text-card-title text-ink">{{ $title }}</h2>
            </div>
        @endif
        <div class="p-5 text-body text-ink">{{ $slot }}</div>
        @isset($footer)
            <div class="flex items-center justify-start gap-2 border-t-2 border-line px-5 py-3">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
