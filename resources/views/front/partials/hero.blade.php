@if ($hero)
    <figure class="front-hero__media">
        @if ($hero['mp4'] || $hero['webm'])
            <video class="front-hero__video" autoplay muted loop playsinline preload="metadata"@if ($hero['poster']) poster="{{ $hero['poster'] }}"@endif @if ($hero['alt'] !== '') aria-label="{{ $hero['alt'] }}"@else aria-hidden="true"@endif>
                @if ($hero['webm'])
                    <source src="{{ $hero['webm'] }}" type="video/webm">
                @endif
                @if ($hero['mp4'])
                    <source src="{{ $hero['mp4'] }}" type="video/mp4">
                @endif
            </video>
        @else
            <img class="front-hero__image" src="{{ $hero['image'] ?? $hero['poster'] }}" alt="{{ $hero['alt'] }}"@if ($hero['width']) width="{{ $hero['width'] }}" height="{{ $hero['height'] }}"@endif fetchpriority="high" decoding="async"@if ($hero['position']) style="object-position: {{ $hero['position'] }}"@endif>
        @endif
        @if ($hero['caption'])
            <figcaption class="front-hero__caption">{{ $hero['caption'] }}</figcaption>
        @endif
    </figure>
@endif
