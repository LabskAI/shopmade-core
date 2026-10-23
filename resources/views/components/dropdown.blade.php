@props([
    'label' => null,
    'icon' => 'ellipsis',
])

<div
    {{ $attributes->class('relative') }}
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    @if ($label)
        <x-button variant="secondary" type="button" @click="open = !open">{{ $label }}</x-button>
    @else
        <x-button variant="secondary" size="icon" type="button" @click="open = !open" aria-label="Open menu">
            <i data-lucide="{{ $icon }}" class="size-4"></i>
        </x-button>
    @endif
    <div
        x-show="open"
        x-cloak
        class="absolute right-0 z-30 mt-1 min-w-44 border-2 border-line bg-surface shadow-md"
    >
        <div class="flex flex-col [&>a]:justify-start [&>a]:px-4 [&>a]:py-2 [&>a]:text-control [&>button]:justify-start [&>button]:px-4 [&>button]:py-2 [&>button]:text-left [&>button]:text-control [&>.danger]:text-accent-700">
            {{ $slot }}
        </div>
    </div>
</div>
