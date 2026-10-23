@extends('layouts.app')

@section('title', 'Customer note')
@section('breadcrumb', 'Customer notes / '.$customer->name)

@section('content')
    <x-page-header title="Note for {{ $customer->name }}" kicker="Features">
        <x-slot:actions>
            <x-button variant="ghost" href="{{ route('customers.show', $customer) }}">Back to customer</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('customer-notes.store', $customer) }}" class="max-w-3xl">
        @csrf
        <x-card title="Note">
            <x-field label="Note" for="body" :error="$errors->first('body')">
                <x-textarea id="body" name="body" rows="8" required :error="$errors->has('body')">{{ old('body', $note->body) }}</x-textarea>
            </x-field>
            <div class="mt-6">
                <x-button type="submit">Save note</x-button>
            </div>
        </x-card>
    </form>
@endsection
