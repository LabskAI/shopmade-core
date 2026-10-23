@props([
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'error' => false,
])

<select {{ $attributes->class([
    'h-10 w-full border-2 bg-surface px-3 text-control text-ink',
    $error ? 'border-accent' : 'border-line',
]) }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
    @endforeach
    {{ $slot }}
</select>
