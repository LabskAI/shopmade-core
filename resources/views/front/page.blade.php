@extends('front.layout')

@section('content')
    @if ($layout === 'home' && $hero)
        <div class="wrap front-hero">
            <div class="front-hero__body prose">
                @if ($hero['kicker'])
                    <p class="front-kicker">{{ $hero['kicker'] }}</p>
                @endif
                {!! $heroHtml !!}
            </div>
            @include('front.partials.hero')
        </div>
        <div class="wrap">
            <article class="prose">
                {!! $html !!}
            </article>
            @include('front.partials.embeds')
        </div>
    @else
        <div class="wrap">
            @if ($hero)
                <div class="front-hero front-hero--banner">
                    @include('front.partials.hero')
                </div>
            @endif
            <article class="prose">
                {!! $html !!}
            </article>
            @include('front.partials.embeds')
        </div>
    @endif
@endsection
