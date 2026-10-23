@extends('layouts.app')

@section('title', 'New customer')
@section('breadcrumb', 'Customers / New')

@section('content')
    <x-page-header title="New customer" kicker="People" />
    <form method="POST" action="{{ route('customers.store') }}" class="max-w-3xl">
        @csrf
        <x-card title="Details">
            @include('customers._form')
            <div class="mt-6 flex justify-start gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" href="{{ route('customers.index') }}">Cancel</x-button>
            </div>
        </x-card>
    </form>
@endsection
