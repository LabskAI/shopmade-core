@extends('layouts.app')

@section('title', 'Customer notes')
@section('breadcrumb', 'Customer notes')

@section('content')
    <x-page-header title="Customer notes" kicker="Features" />

    <div class="border-2 border-line bg-surface">
        @if ($notes->isEmpty())
            <x-empty title="No customer notes yet" body="Open a customer and click Customer notes." />
        @else
            @foreach ($notes as $note)
                <a href="{{ route('customer-notes.customer', $note->customer) }}" class="block border-b border-line-soft px-5 py-4 hover:bg-bg">
                    <div class="text-control text-ink">{{ $note->customer->name }}</div>
                    <div class="mt-1 line-clamp-2 text-body text-ink/55">{{ $note->body }}</div>
                </a>
            @endforeach
            {{ $notes->links() }}
        @endif
    </div>
@endsection
