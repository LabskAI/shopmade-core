@extends('layouts.marketing')

@section('title', 'Forgot password — '.config('app.name'))
@section('description', 'Get a link to reset your '.config('app.name').' password.')

@section('content')
<main class="overflow-hidden bg-gray-50">
    <div class="isolate flex min-h-dvh items-center justify-center p-6 lg:p-8">
        <div class="w-full max-w-md bg-white shadow-md ring-1 ring-black/5">
            <form method="POST" action="{{ route('password.email') }}" class="p-7 sm:p-11">
                @csrf
                <div class="flex items-start">
                    <a href="{{ route('login') }}" title="Sign in">
                        <x-wordmark size="md" />
                    </a>
                </div>
                <h1 class="mt-8 text-base/6 font-medium">Forgot your password?</h1>
                <p class="mt-1 text-sm/5 text-gray-600">Enter your email and we'll send you a link to choose a new one.</p>
                @if (session('status'))
                    <p class="mt-4 text-sm text-green-700">{{ session('status') }}</p>
                @endif

                <div class="mt-8 space-y-3">
                    <label for="email" class="text-sm/5 font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="block w-full border border-transparent px-[calc(--spacing(2)-1px)] py-[calc(--spacing(1.5)-1px)] text-base/6 shadow-sm ring-1 ring-black/10 focus:outline-2 focus:-outline-offset-1 focus:outline-black sm:text-sm/6">
                    @error('email')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-8">
                    <x-marketing.button type="submit" class="w-full">Send reset link</x-marketing.button>
                </div>

                <p class="mt-6 text-center text-sm/5 text-gray-600"><a href="{{ route('login') }}" class="font-medium text-gray-950 hover:text-gray-600">Back to sign in</a></p>
            </form>
        </div>
    </div>
</main>
@endsection
