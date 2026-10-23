@props(['placement' => 'top right'])

@php
    [$yAxis, $xAxis] = explode(' ', $placement);
    $yClass = $yAxis === 'top' ? '-top-2' : '-bottom-2';
    $xClass = $xAxis === 'left' ? '-left-2' : '-right-2';
@endphp

<svg viewBox="0 0 15 15" aria-hidden="true" {{ $attributes->class(['absolute size-[15px] fill-black/10', $yClass, $xClass]) }}>
    <path d="M8 0H7V7H0V8H7V15H8V8H15V7H8V0Z" />
</svg>
