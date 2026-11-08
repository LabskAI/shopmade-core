{{-- One form from facts.forms: the built-in contact form, the AIFormFlow embed when live (form.js mounts on data-aiformflow), else a link to the hosted form. --}}
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
    @if ($embed['builtin'] ?? false)
        @php
            $fieldId = 'form-'.$embed['slug'];
            $formState = request()->query('form') === $embed['slug'] ? (string) request()->query('status') : '';
        @endphp
        @if ($formState === 'sent')
            <p class="front-form__notice" role="status">{{ $t['form_sent'] }}</p>
        @else
            @if ($formState === 'invalid')
                <p class="front-form__notice front-form__notice--error" role="alert">{{ $t['form_invalid'] }}</p>
            @endif
            <form class="front-form__fields" method="post" action="{{ $embed['action'] }}">
                <input type="hidden" name="page" value="{{ url()->current() }}">
                <input type="hidden" name="lang" value="{{ $lang }}">
                <input type="hidden" name="token" value="{{ \App\Core\Front\FrontContactForm::token() }}">
                <p class="front-form__trap" aria-hidden="true"><label for="{{ $fieldId }}-website">Website</label><input id="{{ $fieldId }}-website" name="{{ \App\Core\Front\FrontContactForm::HONEYPOT }}" type="text" tabindex="-1" autocomplete="off"></p>
                <p class="front-form__field"><label for="{{ $fieldId }}-name">{{ $t['form_name'] }}</label><input id="{{ $fieldId }}-name" name="name" type="text" required maxlength="120" autocomplete="name"></p>
                <p class="front-form__field"><label for="{{ $fieldId }}-email">{{ $t['form_email'] }}</label><input id="{{ $fieldId }}-email" name="email" type="email" required maxlength="255" autocomplete="email"></p>
                <p class="front-form__field"><label for="{{ $fieldId }}-phone">{{ $t['form_phone'] }}</label><input id="{{ $fieldId }}-phone" name="phone" type="tel" maxlength="40" autocomplete="tel"></p>
                <p class="front-form__field"><label for="{{ $fieldId }}-message">{{ $t['form_message'] }}</label><textarea id="{{ $fieldId }}-message" name="message" required maxlength="5000" rows="5"></textarea></p>
                <p><button class="btn btn-primary" type="submit">{{ $t['form_send'] }}</button></p>
            </form>
        @endif
    @elseif ($embed['live'])
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
