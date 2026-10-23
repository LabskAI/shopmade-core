@extends('layouts.app')

@section('title', 'Website discovery')
@section('breadcrumb', 'Website / Discovery')

@section('content')
    <x-page-header title="Discovery" kicker="Website" />

    @include('site._tabs')

    <p class="mb-4 max-w-3xl text-body text-ink/70">How AI search, assistants and training crawlers may use your site. robots.txt, sitemap.xml, llms.txt and llms-full.txt are generated from your pages and facts; you set the policy here.</p>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            <form method="POST" action="{{ route('site.discovery.update') }}" x-data="{ sending: false }" x-on:submit="sending = true">
                @csrf
                @method('PUT')
                <x-card title="AI policy">
                    <fieldset class="space-y-4">
                        <div>
                            <x-checkbox name="ai_search" label="Allow AI search and assistants" :checked="old('ai_search', $aiSearch)" />
                            <p class="mt-1 text-label text-ink/55">ChatGPT search, Claude, Perplexity and others may read your pages to answer people's questions and link to you.</p>
                        </div>
                        <div>
                            <x-checkbox name="ai_training" label="Allow AI training" :checked="old('ai_training', $aiTraining)" />
                            <p class="mt-1 text-label text-ink/55">Crawlers that collect pages to train models (GPTBot, ClaudeBot, Google-Extended, CCBot and others).</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 border-t-2 border-line pt-4">
                            <x-button type="submit" x-bind:disabled="sending"><i data-lucide="save" class="size-4"></i>Save policy</x-button>
                            <p class="text-label text-ink/55">Stored in facts.yaml (discovery). Live as soon as you save.</p>
                        </div>
                    </fieldset>
                </x-card>
            </form>

            <x-card title="Agent-readiness check">
                <p class="text-body text-ink/70">Runs the readiness tests against your live front at <span class="break-all font-mono text-label">{{ $liveUrl }}</span>: every page answers, robots.txt, sitemap.xml and llms.txt are in place, and markdown is served to agents.</p>
                <form method="POST" action="{{ route('site.discovery.check') }}" class="mt-4" x-data="{ sending: false }" x-on:submit="sending = true">
                    @csrf
                    <x-button type="submit" variant="secondary" x-bind:disabled="sending">
                        <span class="inline-flex items-center gap-2" x-show="! sending"><i data-lucide="activity" class="size-4"></i>Check the live site</span>
                        <span class="inline-flex items-center gap-2" x-show="sending" x-cloak><i data-lucide="loader-circle" class="size-4 animate-spin"></i>Checking…</span>
                    </x-button>
                </form>

                @if (is_array($readiness))
                    <div class="mt-4 border-t-2 border-line pt-4" data-readiness="{{ $readiness['ok'] ? 'passed' : 'failed' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status :state="$readiness['ok'] ? 'passed' : 'failed'" />
                            <span class="text-label text-ink/55">{{ \Illuminate\Support\Carbon::parse($readiness['ran_at'])->diffForHumans() }}</span>
                        </div>
                        <ul class="mt-3 space-y-1">
                            @foreach ($readiness['checks'] as $check)
                                <li class="flex items-start gap-2 text-control">
                                    <i data-lucide="{{ $check['ok'] ? 'check' : 'x' }}" class="mt-0.5 size-4 shrink-0 {{ $check['ok'] ? 'text-ok-700' : 'text-accent-700' }}"></i>
                                    <span class="min-w-0 break-words"><span class="font-extrabold">{{ $check['name'] }}</span> · {{ $check['detail'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-card>
        </div>

        <x-card title="Published now">
            <ul class="space-y-1">
                @foreach ($liveFiles as $file => $url)
                    <li><a class="break-all text-control text-accent-700 underline hover:text-accent" href="{{ $url }}" target="_blank" rel="noreferrer">{{ $file }}</a></li>
                @endforeach
            </ul>
            @if ($liveRobots !== null)
                <p class="mt-4 text-label text-ink/70">robots.txt on the live site</p>
                <pre class="mt-1 max-h-96 overflow-auto whitespace-pre-wrap break-all bg-bg p-3 font-mono text-label text-ink/70">{{ $liveRobots }}</pre>
            @endif
        </x-card>
    </div>
@endsection
