@props([
    'label' => null,
    'checked' => false,
])

<label {{ $attributes->except(['name', 'value', 'checked'])->class('ds-check inline-flex items-center justify-start gap-2 text-control') }}>
    <input
        type="checkbox"
        name="{{ $attributes->get('name') }}"
        value="{{ $attributes->get('value', 1) }}"
        @checked($checked)
        class="size-5 shrink-0 border-2 border-line bg-surface accent-accent"
    >
    @if ($label)
        <span>{{ $label }}</span>
    @endif
</label>
