@props([
    'variant' => 'primary',
    'href' => null,
])

@php
    $classes = match ($variant) {
        'secondary' => 'relative inline-flex items-center justify-center px-4 py-[calc(--spacing(2)-1px)] border border-transparent bg-white/15 shadow-md ring-1 ring-[#D15052]/15 after:pointer-events-none after:absolute after:inset-0 after:shadow-[inset_0_0_2px_1px_#ffffff4d] text-base font-medium whitespace-nowrap text-gray-950 hover:bg-white/20',
        'outline' => 'inline-flex items-center justify-center px-2 py-[calc(--spacing(1.5)-1px)] border border-transparent shadow-sm ring-1 ring-black/10 text-sm font-medium whitespace-nowrap text-gray-950 hover:bg-gray-50',
        default => 'inline-flex items-center justify-center px-4 py-[calc(--spacing(2)-1px)] border border-transparent bg-gray-950 shadow-md text-base font-medium whitespace-nowrap text-white hover:bg-gray-800',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
