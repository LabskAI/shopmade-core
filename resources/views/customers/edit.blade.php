@extends('layouts.app')

@section('title', 'Edit customer')
@section('breadcrumb', 'Customers / Edit')

@section('content')
    <x-page-header title="Edit {{ $customer->name }}" kicker="People" />
    <form method="POST" action="{{ route('customers.update', $customer) }}" class="max-w-3xl">
        @csrf
        @method('PUT')
        <x-card title="Details">
            @include('customers._form')
            <div class="mt-6 flex justify-start gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" href="{{ route('customers.show', $customer) }}">Cancel</x-button>
            </div>
        </x-card>
    </form>
@endsection
