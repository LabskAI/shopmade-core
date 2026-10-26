<details class="{{ $class }}">
    <summary class="front-item__question">{{ $attrs['question'] ?? '' }}<span class="front-item__toggle" aria-hidden="true">{!! \App\Core\Front\FrontIcons::svg('caret-down', 'md') !!}</span></summary>
    <div class="front-item__answer">
        {!! $html !!}
    </div>
</details>
