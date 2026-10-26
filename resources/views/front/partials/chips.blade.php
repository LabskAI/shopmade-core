@if (($chips ?? []) !== [])
    <ul class="front-chips">
        @foreach ($chips as $chip)
            <li class="front-chip{{ filled($variant ?? null) ? ' front-chip--'.str_replace(' ', ' front-chip--', $variant) : '' }}">{{ $chip }}</li>
        @endforeach
    </ul>
@endif
