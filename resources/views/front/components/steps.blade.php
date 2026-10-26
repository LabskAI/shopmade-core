<div class="{{ $class }}"@if (filled($attrs['id'] ?? null)) id="{{ $attrs['id'] }}"@endif data-reveal>
    @if (trim($html) !== '')
        <div class="front-steps__intro">{!! $html !!}</div>
    @endif
    <ol class="front-steps__list">
        @foreach ($items as $item)
            <li class="{{ $item['class'] }}">
                <span class="front-step__number" aria-hidden="true">{{ $loop->iteration }}</span>
                @include('front.partials.step-body', $item)
            </li>
        @endforeach
    </ol>
</div>
