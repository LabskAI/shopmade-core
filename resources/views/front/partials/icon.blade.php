{{-- A component icon: a kit icon name (FrontIcons) or a company image under assets/ (icon="asset:icons/x.svg"). --}}
@if (filled($attrs['icon_src'] ?? null))
    <span class="front-icon"><img src="{{ $attrs['icon_src'] }}" alt="" width="{{ $px ?? 24 }}" height="{{ $px ?? 24 }}" loading="lazy" decoding="async"></span>
@elseif (filled($attrs['icon'] ?? null))
    <span class="front-icon">{!! \App\Core\Front\FrontIcons::svg($attrs['icon'], $size ?? 'lg') !!}</span>
@endif
