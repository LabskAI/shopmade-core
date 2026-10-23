{{-- Website editor sub-navigation (Admin → Website). Each tab is its own route. --}}
@php
    $frontTabs = [
        ['route' => 'site.pages.index', 'pattern' => 'site.pages.*', 'label' => 'Pages'],
        ['route' => 'site.facts.edit', 'pattern' => 'site.facts.*', 'label' => 'Facts'],
        ['route' => 'site.theme.edit', 'pattern' => 'site.theme.*', 'label' => 'Theme'],
        ['route' => 'site.forms.show', 'pattern' => 'site.forms.*', 'label' => 'Forms'],
        ['route' => 'site.discovery.show', 'pattern' => 'site.discovery.*', 'label' => 'Discovery'],
    ];
@endphp

<nav aria-label="Website" class="mb-4 flex gap-0 overflow-x-auto border-b-2 border-line">
    @foreach ($frontTabs as $frontTab)
        @php($isActive = request()->routeIs($frontTab['pattern']))
        <a
            href="{{ route($frontTab['route']) }}"
            @if ($isActive) aria-current="page" @endif
            class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap px-3 py-2 text-control {{ $isActive ? 'border-b-2 border-accent font-extrabold text-ink' : 'text-ink/70 hover:text-ink' }}"
        >
            {{ $frontTab['label'] }}
        </a>
    @endforeach
</nav>
