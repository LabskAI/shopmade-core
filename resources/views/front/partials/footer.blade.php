<footer class="site-footer">
    <div class="wrap">
        <div class="footer-grid">
            <section>
                <h2>{{ $name }}</h2>
                <ul>
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
                    <ul>
                        @foreach ($cities as $city)
                            <li>{{ $city }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($services !== [])
                <section>
                    <h2>{{ $t['services'] }}</h2>
                    <ul>
                        @foreach ($services as $service)
                            <li>{{ $service }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($hours !== [])
                <section>
                    <h2>{{ $t['hours'] }}</h2>
                    <ul>
                        @foreach ($hours as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($social !== [])
                <section>
                    <h2>{{ $t['follow'] }}</h2>
                    <ul>
                        @foreach ($social as $link)
                            <li><a href="{{ $link['url'] }}" rel="me noopener">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <p class="legal">&copy; {{ $year }} {{ $legalName }}</p>
    </div>
</footer>
