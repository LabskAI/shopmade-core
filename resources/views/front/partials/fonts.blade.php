{{-- Self-hosted faces from facts.fonts.faces (woff2 under assets/). No font CDN. --}}
@if ($fonts['preload'])
    <link rel="preload" href="{{ $fonts['preload'] }}" as="font" type="font/woff2" crossorigin>
@endif
@if ($fonts['faces'] !== [])
    <style>
        @foreach ($fonts['faces'] as $face)
            @font-face { font-family: {!! json_encode($face['family'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}; src: url({!! json_encode($face['url'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}) format("woff2"); font-weight: {{ $face['weight'] }}; font-style: {{ $face['style'] }}; font-stretch: {{ $face['stretch'] }}; font-display: swap; }
        @endforeach
    </style>
@endif
