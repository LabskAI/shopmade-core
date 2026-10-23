@props([
    'columns' => [],
])

@php
    $isEmpty = trim((string) $slot) === '';
@endphp

<div {{ $attributes->class('overflow-x-auto') }}>
    @if ($isEmpty)
        {{ $empty ?? '' }}
    @else
        <table class="min-w-full text-left">
            <thead>
                <tr class="border-b-2 border-line">
                    @foreach ($columns as $column)
                        @php
                            $align = $column['align'] ?? 'left';
                        @endphp
                        <th class="px-4 py-3 text-micro uppercase text-ink/70 {{ $align === 'right' ? 'text-right' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    @endif
</div>
