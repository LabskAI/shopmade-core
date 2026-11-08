{{-- Cards joined by arrows (a process or system diagram). Arrows are decoration; the list order carries the meaning. --}}
<div class="{{ $class }} an-rise"@if (filled($attrs['id'] ?? null)) id="{{ $attrs['id'] }}"@endif data-reveal>
    @if (trim($html) !== '')
        <div class="front-flow__intro">{!! $html !!}</div>
    @endif
    <ol class="front-flow__list f fw ac g2"@if (filled($attrs['label'] ?? null)) aria-label="{{ $attrs['label'] }}"@endif>
        @foreach ($items as $item)
            @if (! $loop->first)
                <li class="front-flow__arrow f cm" aria-hidden="true">{!! \App\Core\Front\FrontIcons::svg('arrow-right', 'lg') !!}</li>
            @endif
            <li class="{{ $item['class'] }} front-flow__step p4 r bg">
                @include('front.partials.step-body', $item)
            </li>
        @endforeach
    </ol>
</div>
