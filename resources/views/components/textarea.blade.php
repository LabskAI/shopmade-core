@props([
    'rows' => 3,
    'error' => false,
])

<textarea rows="{{ $rows }}" {{ $attributes->class([
    'w-full border-2 bg-surface px-3 py-2 text-control text-ink',
    $error ? 'border-accent' : 'border-line',
]) }}>{{ $slot }}</textarea>
