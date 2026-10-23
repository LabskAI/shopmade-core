@props([
    'tabs' => [],
    'active' => null,
])

<nav {{ $attributes->class('flex gap-0 border-b-2 border-line') }}>
    @foreach ($tabs as $slug => $label)
        <a
            href="{{ request()->fullUrlWithQuery(['tab' => $slug]) }}"
            class="px-4 py-3 text-control {{ (string) $active === (string) $slug ? 'border-b-2 border-accent font-extrabold text-ink' : 'text-ink/70 hover:text-ink' }}"
        >{{ $label }}</a>
    @endforeach
</nav>
