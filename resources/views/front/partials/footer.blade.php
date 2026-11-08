<footer class="site-footer bs py8 t1">
    <div class="wrap">
        <div class="footer-grid gr g5">
            @foreach ($footerColumns as $column)
                <section>
                    @if ($column['title'] !== '')
                        <h2>{{ $column['title'] }}</h2>
                    @endif
                    <ul class="sy1">
                        @foreach ($column['links'] as $link)
                            <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <section>
                <h2>{{ $name }}</h2>
                <ul class="sy1">
                    @if ($phone)
                        <li><a href="tel:{{ $phoneHref }}">{{ $phone }}</a></li>
                    @endif
                    @if ($email)
                        <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                    @endif
                    @if ($address)
                        <li>{{ $address }}</li>
                    @endif
                </ul>
            </section>

            @if ($cities !== [])
                <section>
                    <h2>{{ $t['service_area'] }}</h2>
                    <ul class="sy1">
                        @foreach ($cities as $city)
                            <li>{{ $city }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($services !== [])
                <section>
                    <h2>{{ $t['services'] }}</h2>
                    <ul class="sy1">
                        @foreach ($services as $service)
                            <li>{{ $service }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($hours !== [])
                <section>
                    <h2>{{ $t['hours'] }}</h2>
                    <ul class="sy1">
                        @foreach ($hours as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($social !== [])
                <section>
                    <h2>{{ $t['follow'] }}</h2>
                    <ul class="sy1">
                        @foreach ($social as $link)
                            <li><a href="{{ $link['url'] }}" rel="me noopener">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <p class="legal mt5 mb0 cm">&copy; {{ $year }} {{ $legalName }}</p>
        @if ($legalLinks !== [])
            <nav class="legal-links" aria-label="{{ $t['legal'] }}">
                <ul class="f fw g4 mt2">
                    @foreach ($legalLinks as $link)
                        <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
</footer>
