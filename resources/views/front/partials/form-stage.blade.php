{{-- One form from facts.forms: the AIFormFlow embed when live (form.js mounts on data-aiformflow), else a link to the hosted form. --}}
@php
    $stageTitle = $embed['title'] ?? (filled($t['form_title']) ? $t['form_title'] : $embed['name']);
    $stageLead = $embed['lead'] ?? (filled($t['form_lead']) ? $t['form_lead'] : null);
@endphp
<div class="front-form{{ filled($class ?? null) ? ' '.$class : '' }}" id="form-{{ $embed['slug'] }}" aria-labelledby="form-{{ $embed['slug'] }}-title">
    <header class="front-form__head">
        <h2 class="front-form__title" id="form-{{ $embed['slug'] }}-title">{{ $stageTitle }}</h2>
        @if (filled($stageLead))
            <p class="front-form__lead">{{ $stageLead }}</p>
        @endif
        {!! $html ?? '' !!}
    </header>
    @if ($embed['live'])
        <div class="front-form__embed" data-aiformflow data-client="{{ $embed['client'] }}" data-form="{{ $embed['form'] }}" data-theme="{{ $embed['theme'] }}" data-lang="{{ $lang }}" lang="{{ $lang }}">
            {{-- Hidden by the kit CSS while form.js mounts; shown only if the form has not appeared after a few seconds. --}}
            <div class="front-form__fallback" role="status">
                @if ($embed['hosted'])
                    <p>{{ $t['form_error'] }} {{ $t['form_error_help'] }}</p>
                    <p><a class="btn btn-primary" href="{{ $embed['hosted'] }}">{{ $t['open_form_page'] }}</a></p>
                @else
                    <p>{{ $t['form_error'] }}</p>
                @endif
            </div>
        </div>
        @if ($embed['hosted'])
            <noscript><p><a href="{{ $embed['hosted'] }}">{{ $t['open_form_page'] }}</a></p></noscript>
        @endif
    @else
        <p class="front-form__link"><a class="btn btn-primary" href="{{ $embed['hosted'] }}">{{ $t['open_form'] }}</a></p>
    @endif
    @if ($phone && $phoneHref)
        <p class="front-form__alt">{{ $t['prefer_to_talk'] }} <a href="tel:{{ $phoneHref }}">{{ $phone }}</a></p>
    @endif
</div>
