@if ($embeds !== [])
    <section class="front-forms" aria-label="{{ $t['open_form'] }}">
        @foreach ($embeds as $embed)
            @include('front.partials.form-stage', ['embed' => $embed, 'html' => '', 'class' => null])
        @endforeach
    </section>
@endif
