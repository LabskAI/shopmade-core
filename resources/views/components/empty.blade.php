@props([
    'title',
    'body' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center px-5 py-10 text-center') }}>
    <p class="text-card-title text-ink">{{ $title }}</p>
    @if ($body)
        <p class="mt-1 max-w-md text-body text-ink/55">{{ $body }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
