@extends('layouts.app')

@php
    $frontmatter = $page['frontmatter'];
    $pageTitle = is_string($frontmatter['title'] ?? null) && trim($frontmatter['title']) !== '' ? $frontmatter['title'] : $page['slug'];
    $pageCities = $frontmatter['cities'] ?? [];
    $values = [
        'title' => is_string($frontmatter['title'] ?? null) ? $frontmatter['title'] : '',
        'description' => is_string($frontmatter['description'] ?? null) ? $frontmatter['description'] : '',
        'nav_label' => is_string($frontmatter['nav_label'] ?? null) ? $frontmatter['nav_label'] : '',
        'cities' => is_array($pageCities) ? implode(', ', array_filter($pageCities, 'is_string')) : (is_string($pageCities) ? $pageCities : ''),
        'body' => $page['body'],
    ];
    $pageRoute = ['lang' => $page['lang'], 'slug' => $page['slug']];
@endphp

@section('title', 'Edit '.$page['slug'].' ('.strtoupper($page['lang']).')')
@section('breadcrumb', 'Website / Pages / '.$page['slug'].' ('.strtoupper($page['lang']).')')

@section('content')
    <x-page-header :title="$pageTitle" :kicker="'Website · '.$page['slug'].' · '.strtoupper($page['lang'])">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('site.pages.index')">All pages</x-button>
            <x-button variant="secondary" :href="route('site.pages.history', $pageRoute)"><i data-lucide="history" class="size-4"></i>History</x-button>
            <x-button variant="ghost" :href="$liveUrl" target="_blank" rel="noreferrer"><i data-lucide="external-link" class="size-4"></i>Live page</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('site._tabs')

    <div class="grid gap-4 lg:grid-cols-3">
        <form method="POST" action="{{ route('site.pages.update', $pageRoute) }}" class="lg:col-span-2" x-data="{ sending: false }" x-on:submit="sending = true">
            @csrf
            @method('PUT')
            <x-card title="Content">
                <fieldset class="space-y-4">
                    @include('site.pages._fields', ['values' => $values, 'cities' => $cities])

                    <div class="flex flex-wrap items-center gap-2 border-t-2 border-line pt-4">
                        <x-button type="submit" x-bind:disabled="sending">
                            <span class="inline-flex items-center gap-2" x-show="! sending"><i data-lucide="save" class="size-4"></i>Save</span>
                            <span class="inline-flex items-center gap-2" x-show="sending" x-cloak><i data-lucide="loader-circle" class="size-4 animate-spin"></i>Saving…</span>
                        </x-button>
                        <p class="text-label text-ink/55">Saving updates the live page. Every save is kept in the history.</p>
                    </div>
                </fieldset>
            </x-card>
        </form>

        <div class="space-y-4">
            <x-card title="Checks">
                @include('site._lint', ['issues' => $lint])
            </x-card>

            <x-card title="Languages">
                <ul class="space-y-2">
                    @foreach ($translations as $language => $exists)
                        <li class="flex items-center justify-between gap-3 border-b border-line-soft pb-2 last:border-b-0 last:pb-0">
                            <span class="text-control">{{ strtoupper($language) }}@if ($language === $page['lang']) <span class="text-label text-ink/55">· this page</span>@endif</span>
                            @if ($language !== $page['lang'] && $exists)
                                <a class="text-control text-accent-700 underline hover:text-accent" href="{{ route('site.pages.edit', ['lang' => $language, 'slug' => $page['slug']]) }}">Edit</a>
                            @elseif (! $exists)
                                <a class="text-control text-accent-700 underline hover:text-accent" href="{{ route('site.pages.create', ['slug' => $page['slug'], 'lang' => $language]) }}">Add</a>
                            @elseif (! $exists)
                                <span class="text-label text-ink/55">Missing</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>

            @if ($page['slug'] !== 'home')
                <x-card title="Delete">
                    <p class="text-body text-ink/70">Removes the {{ strtoupper($page['lang']) }} version from the live site. Its last version stays in the history.</p>
                    <x-button class="mt-3" type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'delete-page')"><i data-lucide="trash-2" class="size-4"></i>Delete page</x-button>
                </x-card>
            @endif
        </div>
    </div>

    @if ($page['slug'] !== 'home')
        <x-modal name="delete-page" title="Delete {{ $page['slug'] }} ({{ strtoupper($page['lang']) }})?">
            <p>The page is removed from the live site right away. You can restore it from its history.</p>
            <x-slot:footer>
                <x-button type="button" variant="secondary" x-on:click="open = false">Cancel</x-button>
                <form method="POST" action="{{ route('site.pages.destroy', $pageRoute) }}">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">Delete page</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    @endif
@endsection
