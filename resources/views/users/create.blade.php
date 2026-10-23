@extends('layouts.app')

@section('title', 'Add user')
@section('breadcrumb', 'Users / Add')

@section('content')
    <x-page-header title="Add user" kicker="People" />
    <form method="POST" action="{{ route('users.store') }}" class="max-w-3xl">
        @csrf
        <x-card title="Details">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Name" name="name" :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name') }}" required />
                </x-field>
                <x-field label="Email" name="email" :error="$errors->first('email')">
                    <x-input type="email" name="email" value="{{ old('email') }}" required />
                </x-field>
                <x-field label="Password" name="password" :error="$errors->first('password')">
                    <x-input type="password" name="password" required />
                </x-field>
                <x-field label="Role" name="role" :error="$errors->first('role')">
                    <x-select name="role" :options="$roles->mapWithKeys(fn ($r) => [$r->value => $r->label()])" :selected="old('role')" required />
                </x-field>
            </div>
            <div class="mt-6 flex justify-start gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" href="{{ route('users.index') }}">Cancel</x-button>
            </div>
        </x-card>
    </form>
@endsection
