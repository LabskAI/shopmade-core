@props([
    'type' => 'text',
    'error' => false,
    'disabled' => false,
])

<input type="{{ $type }}" @disabled($disabled) {{ $attributes->class([
    'h-10 w-full border-2 bg-surface px-3 text-control text-ink disabled:opacity-50',
    $error ? 'border-accent' : 'border-line',
]) }}>
