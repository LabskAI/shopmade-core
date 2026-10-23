<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $audience = $audience ?? (auth()->user()?->isUser() ? 'field' : 'office');
@endphp
<body class="min-h-full bg-bg font-sans text-body text-ink antialiased {{ $audience === 'field' ? 'ds-field-mode' : '' }}" x-data="{ sidebarOpen: false }">
    <div class="min-h-dvh lg:flex">
        <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-ink/40 lg:hidden" @click="sidebarOpen = false"></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r-2 border-line bg-surface lg:static lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex items-center justify-start border-b-2 border-line px-4 py-4">
                <a href="{{ route('dashboard') }}" class="text-ink hover:text-ink">
                    <x-wordmark />
                </a>
            </div>
            <nav class="flex-1 overflow-y-auto">
                @foreach (($navCoreGroups ?? []) as $group => $links)
                    <p class="px-4 pt-5 pb-1 text-micro uppercase text-ink/45">{{ $group }}</p>
                    @foreach ($links as $tool)
                        <x-nav-link href="{{ route($tool['route']) }}" :active="request()->routeIs($tool['pattern'])">{{ $tool['label'] }}</x-nav-link>
                    @endforeach
                @endforeach

                @if (($navPlugins ?? []) !== [])
                    <p class="px-4 pt-5 pb-1 text-micro uppercase text-ink/45">Features</p>
                    @foreach ($navPlugins as $plugin)
                        <x-nav-link href="{{ route($plugin['route']) }}" :active="request()->routeIs($plugin['pattern'])">{{ $plugin['label'] }}</x-nav-link>
                    @endforeach
                @endif

                @auth
                    @if (auth()->user()->can('manage-plugins') || auth()->user()->can('manage-site') || auth()->user()->can('users.manage') || auth()->user()->can('site.edit'))
                        <p class="px-4 pt-5 pb-1 text-micro uppercase text-ink/45">Admin</p>
                    @endif
                    @if (auth()->user()->can('users.manage'))
                        <x-nav-link href="{{ route('users.index') }}" :active="request()->routeIs('users.*')">Users</x-nav-link>
                    @endif
                    @if (auth()->user()->can('site.edit'))
                        <x-nav-link href="{{ route('site.pages.index') }}" :active="request()->routeIs('site.*')">Website</x-nav-link>
                    @endif
                    @if (auth()->user()->can('manage-plugins'))
                        <x-nav-link href="{{ route('admin.plugins.index') }}" :active="request()->routeIs('admin.plugins.*')">Plugins</x-nav-link>
                    @endif
                    @if (auth()->user()->can('connectors.manage'))
                        <x-nav-link href="{{ route('admin.connectors.index') }}" :active="request()->routeIs('admin.connectors.*')">Connectors</x-nav-link>
                    @endif
                    @if (auth()->user()->can('manage-site'))
                        <x-nav-link href="{{ route('admin.settings.edit') }}" :active="request()->routeIs('admin.settings.*')">Settings</x-nav-link>
                    @endif
                @endauth
            </nav>
            <div class="border-t-2 border-line px-4 py-3 text-label text-ink/55">
                <a href="{{ route('account.edit') }}" class="text-control text-ink hover:underline">{{ auth()->user()->name }}</a>
                <div>{{ auth()->user()->role->label() }} · {{ config('app.org_name') }}</div>
            </div>
        </aside>

        <div class="flex min-h-dvh flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-14 items-center justify-between border-b-2 border-line bg-bg px-4">
                <button type="button" class="ds-hit inline-flex size-10 items-center justify-center text-ink lg:hidden" @click="sidebarOpen = true" aria-label="Open menu">
                    <i data-lucide="menu" class="size-4"></i>
                </button>
                <p class="hidden text-label text-ink/55 lg:block">@yield('breadcrumb', 'Home')</p>
                <div class="flex items-center justify-start gap-2">
                    @auth
                        <x-button variant="ghost" href="{{ route('home') }}">
                            View site
                        </x-button>
                    @endauth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button variant="ghost" type="submit">Log out</x-button>
                    </form>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 pb-24 lg:px-8 lg:pb-8">
                @if ($errors->any())
                    <div class="mb-4">
                        <x-alert tone="bad" title="Could not save">{{ $errors->first() }}</x-alert>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-3 border-t-2 border-line bg-surface lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex min-h-14 flex-col items-center justify-center text-micro uppercase {{ request()->routeIs('dashboard') ? 'text-ink' : 'text-ink/55' }}">Home</a>
        <a href="{{ route('customers.index') }}" class="flex min-h-14 flex-col items-center justify-center text-micro uppercase {{ request()->routeIs('customers.*') ? 'text-ink' : 'text-ink/55' }}">Customers</a>
        @if (Route::has('site.pages.index'))
            <a href="{{ route('site.pages.index') }}" class="flex min-h-14 flex-col items-center justify-center text-micro uppercase {{ request()->routeIs('site.*') ? 'text-ink' : 'text-ink/55' }}">Website</a>
        @endif
    </nav>

    <x-toast-host />

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>
