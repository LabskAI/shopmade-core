@extends($user->isClient() ? 'layouts.portal' : 'layouts.app')

@section('title', 'My account')
@section('breadcrumb', 'My account')

@section('content')
    <x-page-header title="My account" kicker="{{ $user->email }}" />
    <form method="POST" action="{{ route('account.password.update') }}" class="max-w-xl">
        @csrf
        @method('PUT')
        <x-card title="Change password">
            <div class="grid gap-4">
                <x-field label="Current password" name="current_password" :error="$errors->first('current_password')">
                    <x-input type="password" name="current_password" autocomplete="current-password" required />
                </x-field>
                <x-field label="New password" name="password" :error="$errors->first('password')" hint="At least 8 characters.">
                    <x-input type="password" name="password" autocomplete="new-password" required />
                </x-field>
                <x-field label="Repeat new password" name="password_confirmation">
                    <x-input type="password" name="password_confirmation" autocomplete="new-password" required />
                </x-field>
            </div>
            <div class="mt-6">
                <x-button type="submit">Change password</x-button>
            </div>
        </x-card>
    </form>
@endsection
