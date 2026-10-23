@foreach ($buttons as $button)
    <x-button variant="secondary" href="{{ $button['url'] }}">{{ $button['label'] }}</x-button>
@endforeach
