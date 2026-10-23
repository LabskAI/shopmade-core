@props([
    'label' => null,
    'for' => null,
    'error' => null,
    'hint' => null,
])

<div {{ $attributes->class('flex flex-col gap-1') }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="text-label text-ink/70">{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($error)
        <p class="text-label text-accent-700">{{ $error }}</p>
    @endif
    @if ($hint)
        <p class="text-label text-ink/55">{{ $hint }}</p>
    @endif
</div>
