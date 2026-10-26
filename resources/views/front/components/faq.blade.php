<div class="{{ $class }}"@if (filled($attrs['id'] ?? null)) id="{{ $attrs['id'] }}"@endif>
    {!! $html !!}
    @foreach ($items as $item)
        @include('front.components.item', $item)
    @endforeach
</div>
