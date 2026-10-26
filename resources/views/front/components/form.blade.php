@include('front.partials.form-stage', ['embed' => $embed, 'html' => $html, 'class' => trim((string) preg_replace('/^front-form(?=\s|$)/', '', $class))])
