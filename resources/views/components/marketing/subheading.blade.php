@props([
    'as' => 'h2',
    'dark' => false,
])

<{{ $as }} {{ $attributes->class('font-mono text-xs/5 font-semibold tracking-widest text-gray-500 uppercase'.($dark ? ' text-gray-400' : '')) }}>
    {{ $slot }}
</{{ $as }}>
