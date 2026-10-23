@extends('layouts.app')

@section('title', 'Website pages')
@section('breadcrumb', 'Website / Pages')

@section('content')
    <x-page-header title="Pages" kicker="Website">
        <x-slot:actions>
            <x-button :href="route('site.pages.create')"><i data-lucide="plus" class="size-4"></i>New page</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('site._tabs')

    <p class="mb-4 max-w-3xl text-body text-ink/70">Every page of your site, one row per page with a button per language. Navigation, footer and the AI-discovery files are built from your pages and facts.</p>

    <div class="border-2 border-line bg-surface">
        @forelse ($pages as $slug => $versions)
            @php
                $firstVersion = $versions->first();
                $pageTitle = is_string($firstVersion['frontmatter']['title'] ?? null) && trim($firstVersion['frontmatter']['title']) !== ''
                    ? $firstVersion['frontmatter']['title']
                    : $slug;
                $pageLanguages = collect($languages)->merge($versions->pluck('lang'))->unique()->values();
            @endphp
            <div class="flex flex-col gap-3 border-b border-line-soft px-4 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between" data-page="{{ $slug }}">
                <div class="min-w-0">
                    <p class="break-words text-control font-extrabold text-ink">{{ $pageTitle }}</p>
                    <p class="font-mono text-label text-ink/55">{{ $slug === 'home' ? '/' : '/'.$slug }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($pageLanguages as $language)
                        @php($version = $versions->firstWhere('lang', $language))
                        @if ($version)
                            <x-button variant="secondary" size="sm" :href="route('site.pages.edit', ['lang' => $language, 'slug' => $slug])" aria-label="Edit {{ $slug }} ({{ strtoupper($language) }})">
                                {{ strtoupper($language) }}
                            </x-button>
                        @else
                            <x-button variant="ghost" size="sm" :href="route('site.pages.create', ['slug' => $slug, 'lang' => $language])" aria-label="Add the {{ strtoupper($language) }} version of {{ $slug }}">
                                <i data-lucide="plus" class="size-4"></i>{{ strtoupper($language) }}
                            </x-button>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <x-empty title="No pages yet" body="Start with the home page: slug home, in your default language.">
                <x-slot:action>
                    <x-button variant="secondary" :href="route('site.pages.create', ['slug' => 'home'])">New page</x-button>
                </x-slot:action>
            </x-empty>
        @endforelse
    </div>
@endsection
