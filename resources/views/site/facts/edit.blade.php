@extends('layouts.app')

@section('title', 'Website facts')
@section('breadcrumb', 'Website / Facts')

@php
    $listOf = fn (mixed $value): string => implode("\n", array_filter(is_array($value) ? $value : [], 'is_string'));
    $serviceRows = collect(old('services', array_map(fn (array $row): array => ['slug' => $row['slug'], 'name' => $row['names']], $services)))
        ->filter(fn (mixed $row): bool => is_array($row) && (filled($row['slug'] ?? null) || collect($row['name'] ?? [])->filter()->isNotEmpty()))
        ->values()
        ->all();
    $blankServiceRows = 5;
@endphp

@section('content')
    <x-page-header title="Facts" kicker="Website" />

    @include('site._tabs')

    <p class="mb-4 max-w-3xl text-body text-ink/70">Facts feed the navigation, the footer, the AI-discovery files and what Claude reads over MCP, in one go. If a fact appears on more than one page, it belongs here. Other keys in facts.yaml (legal name, address, social links) are kept as they are.</p>

    <div class="grid gap-4 lg:grid-cols-3">
        <form method="POST" action="{{ route('site.facts.update') }}" class="lg:col-span-2" x-data="{ sending: false }" x-on:submit="sending = true">
            @csrf
            @method('PUT')
            <fieldset class="space-y-4">
                <x-card title="Business">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field class="sm:col-span-2" label="Business name" for="name" :error="$errors->first('name')">
                            <x-input id="name" name="name" maxlength="200" required :error="$errors->has('name')" value="{{ old('name', is_string($facts['name'] ?? null) ? $facts['name'] : '') }}" />
                        </x-field>
                        <x-field label="Phone" for="phone" :error="$errors->first('phone')">
                            <x-input id="phone" name="phone" type="tel" maxlength="50" :error="$errors->has('phone')" value="{{ old('phone', is_scalar($facts['phone'] ?? null) ? (string) $facts['phone'] : '') }}" />
                        </x-field>
                        <x-field label="Email" for="email" :error="$errors->first('email')">
                            <x-input id="email" name="email" type="email" maxlength="200" :error="$errors->has('email')" value="{{ old('email', is_string($facts['email'] ?? null) ? $facts['email'] : '') }}" />
                        </x-field>
                        <x-field label="Languages" for="languages" :error="$errors->first('languages')" hint="Two-letter codes, the default first, for example: en, es.">
                            <x-input id="languages" name="languages" maxlength="100" required autocapitalize="none" :error="$errors->has('languages')" value="{{ old('languages', implode(', ', $languages)) }}" />
                        </x-field>
                        <x-field label="Hours" for="hours" :error="$errors->first('hours')" hint="One line each, for example Mo-Fr 08:00-18:00.">
                            <x-textarea id="hours" name="hours" rows="3" maxlength="2000" :error="$errors->has('hours')">{{ old('hours', $listOf($facts['hours'] ?? [])) }}</x-textarea>
                        </x-field>
                        <x-field class="sm:col-span-2" label="Cities" for="cities" :error="$errors->first('cities')" hint="One per line. A page can only claim a city listed here.">
                            <x-textarea id="cities" name="cities" rows="4" maxlength="5000" :error="$errors->has('cities')">{{ old('cities', $listOf($facts['cities'] ?? [])) }}</x-textarea>
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Services">
                    <p class="mb-4 text-label text-ink/55">The slug names the service in pages; the name is shown per language. A name given only in {{ strtoupper($languages[0]) }} is used for every language. To remove a service, clear its slug and names.</p>
                    <div class="space-y-4" x-data="{ shown: {{ $serviceRows === [] ? 1 : 0 }} }">
                        @foreach ($serviceRows as $index => $row)
                            @include('site.facts._service', ['index' => $index, 'slug' => (string) ($row['slug'] ?? ''), 'names' => (array) ($row['name'] ?? [])])
                        @endforeach
                        @foreach (range(0, $blankServiceRows - 1) as $blank)
                            <div x-show="shown > {{ $blank }}" @if ($blank > 0 || $serviceRows !== []) x-cloak @endif>
                                @include('site.facts._service', ['index' => count($serviceRows) + $blank, 'slug' => '', 'names' => []])
                            </div>
                        @endforeach
                        <x-button type="button" variant="secondary" size="sm" x-show="shown < {{ $blankServiceRows }}" x-on:click="shown++"><i data-lucide="plus" class="size-4"></i>Add a service</x-button>
                    </div>
                </x-card>

                <x-card title="Never write">
                    <x-field label="Phrases the site must never use" for="never_write" :error="$errors->first('never_write')" hint="One per line: certifications you do not hold, star ratings, guarantees. A page that uses one is flagged in the checks.">
                        <x-textarea id="never_write" name="never_write" rows="8" maxlength="10000" :error="$errors->has('never_write')">{{ old('never_write', $listOf($facts['never_write'] ?? [])) }}</x-textarea>
                    </x-field>
                </x-card>

                <div class="flex flex-wrap items-center gap-2">
                    <x-button type="submit" x-bind:disabled="sending">
                        <span class="inline-flex items-center gap-2" x-show="! sending"><i data-lucide="save" class="size-4"></i>Save facts</span>
                        <span class="inline-flex items-center gap-2" x-show="sending" x-cloak><i data-lucide="loader-circle" class="size-4 animate-spin"></i>Saving…</span>
                    </x-button>
                    <p class="text-label text-ink/55">Comments inside facts.yaml, other than the block at the top, are not kept.</p>
                </div>
            </fieldset>
        </form>

        <div class="space-y-4">
            <x-card title="Checks">
                @include('site._lint', ['issues' => $lint, 'showFile' => true])
            </x-card>
        </div>
    </div>
@endsection
