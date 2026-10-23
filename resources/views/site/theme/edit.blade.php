@extends('layouts.app')

@section('title', 'Website theme')
@section('breadcrumb', 'Website / Theme')

@section('content')
    <x-page-header title="Theme" kicker="Website">
        <x-slot:actions>
            <x-button variant="ghost" :href="$liveUrl" target="_blank" rel="noreferrer"><i data-lucide="external-link" class="size-4"></i>Live site</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('site._tabs')

    @if (! $hasTheme)
        <div class="border-2 border-line bg-surface">
            <x-empty title="This front has no theme tokens" body="The site uses the kit's defaults. Add a theme/tokens.css to the front; its values can then be edited here." />
        </div>
    @else
        <p class="mb-4 max-w-3xl text-body text-ink/70">The colors, fonts, type scale and spacing of your site, as CSS values (for example #146b57, 1.25rem, Georgia, serif). Only the tokens below can be changed here. Saving updates the live site.</p>

        <form method="POST" action="{{ route('site.theme.update') }}" class="max-w-4xl" x-data="{ sending: false }" x-on:submit="sending = true">
            @csrf
            @method('PUT')
            <fieldset class="space-y-4">
                @foreach ($groups as $groupName => $groupTokens)
                    <x-card :title="$groupName">
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($groupTokens as $token)
                                @php($fieldId = 'token'.str_replace('-', '_', $token['name']))
                                <x-field :label="$token['name']" :for="$fieldId" :error="$errors->first('tokens.'.$token['name'])">
                                    <div class="flex items-center gap-2">
                                        @if ($token['color'])
                                            <span class="size-10 shrink-0 border-2 border-line" style="background: {{ $token['value'] }}" aria-hidden="true"></span>
                                        @endif
                                        <x-input
                                            :id="$fieldId"
                                            name="tokens[{{ $token['name'] }}]"
                                            maxlength="{{ \App\Core\Front\FrontThemeTokens::MAX_VALUE_LENGTH }}"
                                            autocapitalize="none"
                                            autocomplete="off"
                                            spellcheck="false"
                                            class="font-mono"
                                            value="{{ old('tokens.'.$token['name'], $token['value']) }}"
                                        />
                                    </div>
                                </x-field>
                            @endforeach
                        </div>
                    </x-card>
                @endforeach

                <div class="flex flex-wrap items-center gap-2">
                    <x-button type="submit" x-bind:disabled="sending">
                        <span class="inline-flex items-center gap-2" x-show="! sending"><i data-lucide="save" class="size-4"></i>Save theme</span>
                        <span class="inline-flex items-center gap-2" x-show="sending" x-cloak><i data-lucide="loader-circle" class="size-4 animate-spin"></i>Saving…</span>
                    </x-button>
                    <p class="text-label text-ink/55">Not allowed in a value: ; { } &lt; &gt; \ url( expression( @@import and comments.</p>
                </div>
            </fieldset>
        </form>
    @endif
@endsection
