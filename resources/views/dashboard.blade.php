@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
    <x-page-header title="Dashboard" kicker="Work">
        <x-slot:actions>
            <x-button href="{{ route('customers.create') }}"><i data-lucide="plus" class="size-4"></i>New customer</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-1 gap-0.5 border-2 border-line bg-line sm:grid-cols-3">
        <x-stat label="Customers" :value="$customerCount" />
        <x-stat label="Client requests this week" :value="$requestCount" />
        <x-stat label="Website pages" :value="$pageCount" />
    </div>

    @foreach ($cards as $card)
        @include($card['view'], $card['data'])
    @endforeach

    @can('site.edit')
        <x-card title="Website" class="mb-6">
            <x-slot:actions>
                <x-button variant="ghost" href="{{ route('site.pages.index') }}">Edit pages</x-button>
            </x-slot:actions>
            <p class="text-body text-ink/70">Edit pages, facts and theme here, or connect Claude under Connectors and edit over MCP. Every save is live and kept in the page history.</p>
        </x-card>
    @endcan
@endsection
