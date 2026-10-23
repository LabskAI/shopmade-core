@props(['active' => false])

<a {{ $attributes->class([
    'flex h-10 items-center justify-start px-3 text-control',
    'bg-ink text-bg hover:text-bg' => $active,
    'text-ink/70 hover:text-ink' => ! $active,
]) }}>{{ $slot }}</a>
