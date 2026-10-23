@extends('layouts.app')

@section('title', 'History of '.$slug.' ('.strtoupper($lang).')')
@section('breadcrumb', 'Website / Pages / '.$slug.' ('.strtoupper($lang).') / History')

@section('content')
    <x-page-header title="History" kicker="Website · {{ $slug }} · {{ strtoupper($lang) }}">
        <x-slot:actions>
            @if ($page)
                <x-button variant="secondary" :href="route('site.pages.edit', ['lang' => $lang, 'slug' => $slug])">Back to the page</x-button>
            @else
                <x-button variant="secondary" :href="route('site.pages.index')">All pages</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @include('site._tabs')

    <p class="mb-4 max-w-3xl text-body text-ink/70">Every saved version of this page, newest first, from the dashboard and from MCP. Restoring a version makes it live right away and adds it to the top of this list.</p>

    <div class="border-2 border-line bg-surface">
        @forelse ($versions as $index => $version)
            <div class="flex flex-col gap-3 border-b border-line-soft px-4 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between" data-version="{{ $version->id }}">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 text-control text-ink">
                        <span class="break-words">{{ $version->note }}</span>
                        @if ($index === 0 && $version->content !== null)
                            <span class="bg-ok-100 px-2 py-1 text-micro uppercase text-ok-800">Live now</span>
                        @endif
                    </p>
                    <p class="mt-1 text-label text-ink/55">
                        <span class="font-mono">#{{ $version->id }}</span>
                        · {{ $version->created_at?->format('M j, Y · g:ia') }}
                        · {{ $version->user?->name ?? $version->actor }}
                        @if ($version->via === 'mcp')
                            · via MCP
                        @endif
                    </p>
                </div>
                @if ($version->content !== null && ! ($index === 0))
                    <form method="POST" action="{{ route('site.pages.restore', ['lang' => $lang, 'slug' => $slug]) }}" x-data="{ sending: false }" x-on:submit="sending = true">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $version->id }}">
                        <x-button type="submit" variant="secondary" size="sm" x-bind:disabled="sending"><i data-lucide="rotate-ccw" class="size-4"></i>Restore</x-button>
                    </form>
                @endif
            </div>
        @empty
            <x-empty title="No saved versions yet" body="Versions appear here after the first save from the dashboard or MCP." />
        @endforelse
    </div>

    @if (count($versions) >= \App\Core\Front\FrontEditor::HISTORY_LIMIT)
        <p class="mt-3 text-label text-ink/55">Showing the newest {{ \App\Core\Front\FrontEditor::HISTORY_LIMIT }} versions.</p>
    @endif
@endsection
