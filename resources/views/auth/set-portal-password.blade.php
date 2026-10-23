@extends('layouts.marketing')

@section('title', 'Set your password — '.config('app.name'))
@section('description', 'Choose a password for the '.config('app.name').' client portal.')

@section('content')
<main class="overflow-hidden bg-gray-50">
    <div class="isolate flex min-h-dvh items-center justify-center p-6 lg:p-8">
        <div class="w-full max-w-md bg-white shadow-md ring-1 ring-black/5">
            <form method="POST" action="{{ $actionUrl }}" class="p-7 sm:p-11">
                @csrf
                <div class="flex items-start">
                    <a href="{{ route('login') }}" title="Sign in">
                        <x-wordmark size="md" />
                    </a>
                </div>
                <h1 class="mt-8 text-base/6 font-medium">Set your password</h1>
                <p class="mt-1 text-sm/5 text-gray-600">Choose a password for {{ $user->email }} so you can sign in to the client portal.</p>

                <div class="mt-8 space-y-3">
                    <label for="password" class="text-sm/5 font-medium">Password</label>
                    <input id="password" name="password" type="password" required autofocus minlength="8" class="block w-full border border-transparent px-[calc(--spacing(2)-1px)] py-[calc(--spacing(1.5)-1px)] text-base/6 shadow-sm ring-1 ring-black/10 focus:outline-2 focus:-outline-offset-1 focus:outline-black sm:text-sm/6">
                    @error('password')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-8 space-y-3">
                    <label for="password_confirmation" class="text-sm/5 font-medium">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" class="block w-full border border-transparent px-[calc(--spacing(2)-1px)] py-[calc(--spacing(1.5)-1px)] text-base/6 shadow-sm ring-1 ring-black/10 focus:outline-2 focus:-outline-offset-1 focus:outline-black sm:text-sm/6">
                </div>

                <div class="mt-8">
                    <x-marketing.button type="submit" class="w-full">Save password</x-marketing.button>
                </div>
            </form>
        </div>
    </div>
</main>
@endsection
