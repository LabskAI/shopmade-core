@if (($chips ?? []) !== [])
    <ul class="front-chips f fw g2">
        @foreach ($chips as $chip)
            <li class="front-chip{{ filled($variant ?? null) ? ' front-chip--'.str_replace(' ', ' front-chip--', $variant) : '' }}">{{ $chip }}</li>
        @endforeach
    </ul>
@endif
