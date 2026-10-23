@extends('layouts.app')

@section('title', 'New page')
@section('breadcrumb', 'Website / Pages / New')

@php
    $sourceTitle = $source['frontmatter']['title'] ?? '';
    $sourceDescription = $source['frontmatter']['description'] ?? '';
    $sourceCities = $source['frontmatter']['cities'] ?? [];
    $values = [
        'title' => is_string($sourceTitle) ? $sourceTitle : '',
        'description' => is_string($sourceDescription) ? $sourceDescription : '',
        'nav_label' => is_string($source['frontmatter']['nav_label'] ?? null) ? $source['frontmatter']['nav_label'] : '',
        'cities' => is_array($sourceCities) ? implode(', ', array_filter($sourceCities, 'is_string')) : '',
        'body' => $source['body'] ?? '',
    ];
@endphp

@section('content')
    <x-page-header title="New page" kicker="Website">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('site.pages.index')">All pages</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('site._tabs')

    <form method="POST" action="{{ route('site.pages.store') }}" x-data="{ sending: false }" x-on:submit="sending = true">
        @csrf
        <x-card title="Page" class="max-w-4xl">
            <fieldset class="space-y-4">
                @if ($source)
                    <x-alert tone="info" title="Prefilled from the {{ strtoupper($source['lang']) }} version">Translate the text before you save: the new version is saved exactly as written.</x-alert>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Address (slug)" for="slug" :error="$errors->first('slug')" hint="Lowercase letters, digits and dashes, for example about-us. The same slug in every language.">
                        <x-input id="slug" name="slug" maxlength="80" required pattern="[a-z0-9]+(-[a-z0-9]+)*" autocapitalize="none" autocomplete="off" :error="$errors->has('slug')" value="{{ old('slug', $slug) }}" />
                    </x-field>
                    <x-field label="Language" for="lang" :error="$errors->first('lang')" hint="Add a language under Facts first.">
                        <x-select id="lang" name="lang" :options="collect($languages)->mapWithKeys(fn ($language) => [$language => strtoupper($language)])" :selected="old('lang', $lang)" :error="$errors->has('lang')" />
                    </x-field>
                </div>

                @include('site.pages._fields', ['values' => $values, 'cities' => $cities])

                <div class="flex flex-wrap items-center gap-2 border-t-2 border-line pt-4">
                    <x-button type="submit" x-bind:disabled="sending">
                        <span class="inline-flex items-center gap-2" x-show="! sending"><i data-lucide="file-plus" class="size-4"></i>Create page</span>
                        <span class="inline-flex items-center gap-2" x-show="sending" x-cloak><i data-lucide="loader-circle" class="size-4 animate-spin"></i>Saving…</span>
                    </x-button>
                    <x-button variant="secondary" :href="route('site.pages.index')">Cancel</x-button>
                </div>
            </fieldset>
        </x-card>
    </form>
@endsection
