@extends('layouts.marketing')

@section('title', 'Invite link unavailable — '.config('app.name'))
@section('description', 'This client portal invite link cannot be used.')

@section('content')
<main class="overflow-hidden bg-gray-50">
    <div class="isolate flex min-h-dvh items-center justify-center p-6 lg:p-8">
        <div class="w-full max-w-md bg-white p-7 shadow-md ring-1 ring-black/5 sm:p-11">
            <a href="{{ route('login') }}" title="Sign in">
                <x-wordmark size="md" />
            </a>
            <h1 class="mt-8 text-base/6 font-medium">Invite link unavailable</h1>
            <p class="mt-2 text-sm/5 text-gray-600">{{ $message }}</p>
            <div class="mt-8">
                <x-marketing.button href="{{ route('login') }}" class="w-full">Go to sign in</x-marketing.button>
            </div>
        </div>
    </div>
</main>
@endsection
