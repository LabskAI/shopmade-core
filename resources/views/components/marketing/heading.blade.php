@props([
    'as' => 'h2',
    'dark' => false,
])

<{{ $as }} {{ $attributes->class([
    'text-4xl font-medium tracking-tighter text-pretty sm:text-6xl',
    $dark ? 'text-white' : 'text-gray-950',
]) }}>
    {{ $slot }}
</{{ $as }}>
