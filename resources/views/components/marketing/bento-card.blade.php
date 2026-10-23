@props([
    'dark' => false,
    'eyebrow',
    'title',
    'description',
    'fade' => [],
])

<div {{ $attributes->class([
    'group relative flex flex-col overflow-hidden',
    'bg-white shadow-xs ring-1 ring-black/5',
    'bg-gray-800 ring-white/15' => $dark,
]) }} @if ($dark) data-dark="true" @endif>
    <div class="relative h-80 shrink-0">
        {{ $slot }}
        @if (in_array('top', $fade, true))
            <div class="absolute inset-0 bg-linear-to-b from-white to-50% {{ $dark ? 'from-gray-800 from-[-25%]' : '' }}"></div>
        @endif
        @if (in_array('bottom', $fade, true))
            <div class="absolute inset-0 bg-linear-to-t from-white to-50% {{ $dark ? 'from-gray-800 from-[-25%]' : '' }}"></div>
        @endif
    </div>
    <div class="relative p-10">
        <x-marketing.subheading as="h3" :dark="$dark">{{ $eyebrow }}</x-marketing.subheading>
        <p class="mt-1 text-2xl/8 font-medium tracking-tight {{ $dark ? 'text-white' : 'text-gray-950' }}">{{ $title }}</p>
        <p class="mt-2 max-w-[600px] text-sm/6 {{ $dark ? 'text-gray-400' : 'text-gray-600' }}">{{ $description }}</p>
    </div>
</div>
