@props([
    'variant' => 'primary',
    'size' => 'default',
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $classes = [
        'ds-btn inline-flex items-center justify-start gap-2 text-control disabled:pointer-events-none disabled:opacity-50',
        match ($size) {
            'sm' => 'h-8 px-3',
            'icon' => 'size-10 justify-center px-0',
            default => 'h-10 px-4',
        },
        match ($variant) {
            'secondary' => 'border-2 border-line bg-transparent text-ink hover:text-ink',
            'ghost' => 'bg-transparent text-accent-700 hover:text-accent',
            default => 'bg-accent text-bg hover:text-bg',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
