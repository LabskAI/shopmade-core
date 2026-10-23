@extends('layouts.app')

@section('title', 'Edit '.$user->name)
@section('breadcrumb', 'Users / '.$user->name)

@section('content')
    <x-page-header title="{{ $user->name }}" kicker="People" />
    <form method="POST" action="{{ route('users.update', $user) }}" class="max-w-3xl">
        @csrf
        @method('PUT')
        <x-card title="Details">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Name" name="name" :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $user->name) }}" required />
                </x-field>
                <x-field label="Email" name="email" :error="$errors->first('email')">
                    <x-input type="email" name="email" value="{{ old('email', $user->email) }}" required />
                </x-field>
                <x-field label="Role" name="role" :error="$errors->first('role')">
                    <x-select name="role" :options="$roles->mapWithKeys(fn ($r) => [$r->value => $r->label()])" :selected="old('role', $user->role->value)" required />
                </x-field>
                <x-field label="New password" name="password" :error="$errors->first('password')" hint="Leave empty to keep the current password.">
                    <x-input type="password" name="password" autocomplete="new-password" />
                </x-field>
            </div>
            <div class="mt-6 flex justify-start gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" href="{{ route('users.index') }}">Cancel</x-button>
            </div>
        </x-card>
    </form>

    @unless ($user->is(auth()->user()))
        <x-card title="Access" class="mt-6 max-w-3xl">
            @if ($user->isDeactivated())
                <p class="text-body text-ink/70">Deactivated {{ $user->deactivated_at->diffForHumans() }}. They cannot sign in or use a connector.</p>
                <form method="POST" action="{{ route('users.reactivate', $user) }}" class="mt-3">
                    @csrf
                    <x-button type="submit" variant="secondary">Reactivate</x-button>
                </form>
            @else
                <p class="text-body text-ink/70">Deactivating signs them out and blocks sign-in and connectors. Their records stay.</p>
                <form method="POST" action="{{ route('users.deactivate', $user) }}" class="mt-3">
                    @csrf
                    <x-button type="submit" variant="danger">Deactivate</x-button>
                </form>
            @endif
        </x-card>
    @endunless
@endsection
