<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Client portal') — {{ config('app.name') }}</title>
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $orgName = config('app.org_name', config('app.name'));
    $portalTabs = [
        ['route' => 'portal.home', 'pattern' => 'portal.home', 'label' => 'Home', 'icon' => 'home'],
        ...app(\App\Core\Packages\Hooks::class)->portalTabs(),
    ];
@endphp
<body class="ds-field-mode min-h-full bg-bg font-sans text-body text-ink antialiased">
    <div class="min-h-dvh md:flex">
        <aside class="hidden w-56 shrink-0 border-r-2 border-line bg-surface md:flex md:flex-col">
            <div class="border-b-2 border-line px-4 py-4">
                <a href="{{ route('portal.home') }}" class="text-control font-semibold text-ink hover:text-ink">{{ $orgName }}</a>
                <p class="mt-1 text-micro uppercase text-ink/45">Client portal</p>
            </div>
            <nav class="flex-1 px-2 py-3">
                @foreach ($portalTabs as $tab)
                    <x-nav-link href="{{ route($tab['route']) }}" :active="request()->routeIs($tab['pattern'])">{{ $tab['label'] }}</x-nav-link>
                @endforeach
            </nav>
            <div class="border-t-2 border-line px-4 py-3 text-label text-ink/55">
                <a href="{{ route('account.edit') }}" class="text-control text-ink hover:underline">{{ auth()->user()->name }}</a>
                <div>Client</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <x-button variant="ghost" type="submit" size="sm">Log out</x-button>
                </form>
            </div>
        </aside>

        <div class="flex min-h-dvh flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-14 items-center justify-between border-b-2 border-line bg-bg px-4 md:hidden">
                <div>
                    <div class="text-control font-semibold">{{ $orgName }}</div>
                    <p class="text-micro uppercase text-ink/45">Client portal</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button variant="ghost" type="submit">Log out</x-button>
                </form>
            </header>

            <main class="flex-1 px-4 py-6 pb-24 md:px-8 md:pb-8">
                @if (session('status'))
                    <div class="mb-4">
                        <x-alert tone="ok" :title="session('status')" />
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-4">
                        <x-alert tone="bad" title="Could not save">{{ $errors->first() }}</x-alert>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-20 flex border-t-2 border-line bg-surface md:hidden">
        @foreach ($portalTabs as $tab)
            <a href="{{ route($tab['route']) }}" class="ds-hit flex min-h-14 flex-1 flex-col items-center justify-center gap-1 text-micro uppercase {{ request()->routeIs($tab['pattern']) ? 'text-ink' : 'text-ink/55' }}">
                <i data-lucide="{{ $tab['icon'] }}" class="size-4"></i>
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>
