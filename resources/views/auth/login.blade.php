@extends('layouts.marketing')

@section('title', 'Log in — '.config('app.name'))
@section('description', 'Sign in to your '.config('app.name').' workspace.')

@section('content')
<main class="overflow-hidden bg-gray-50">
    <div class="relative mx-auto max-w-7xl">
        <div class="absolute -top-44 -right-60 h-60 w-xl rotate-[-10deg] bg-linear-115 from-accent-100 from-28% via-accent-400 via-70% to-accent blur-3xl transform-gpu md:right-0"></div>
    </div>
    <div class="isolate flex min-h-dvh items-center justify-center p-6 lg:p-8">
        <div class="w-full max-w-md bg-white shadow-md ring-1 ring-black/5">
            <form method="POST" action="{{ route('login') }}" class="p-7 sm:p-11">
                @csrf
                <div class="flex items-start">
                    <a href="{{ route('home') }}" title="Home">
                        <x-wordmark size="md" />
                    </a>
                </div>
                <h1 class="mt-8 text-base/6 font-medium">Welcome back!</h1>
                <p class="mt-1 text-sm/5 text-gray-600">Sign in to your workspace.</p>
                @if (session('status'))
                    <p class="mt-4 text-sm text-green-700">{{ session('status') }}</p>
                @endif

                <div class="mt-8 space-y-3">
                    <label for="email" class="text-sm/5 font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="block w-full border border-transparent px-[calc(--spacing(2)-1px)] py-[calc(--spacing(1.5)-1px)] text-base/6 shadow-sm ring-1 ring-black/10 focus:outline-2 focus:-outline-offset-1 focus:outline-black sm:text-sm/6">
                    @error('email')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-8 space-y-3">
                    <label for="password" class="text-sm/5 font-medium">Password</label>
                    <input id="password" name="password" type="password" required class="block w-full border border-transparent px-[calc(--spacing(2)-1px)] py-[calc(--spacing(1.5)-1px)] text-base/6 shadow-sm ring-1 ring-black/10 focus:outline-2 focus:-outline-offset-1 focus:outline-black sm:text-sm/6">
                </div>

                <div class="mt-8 flex items-center justify-between text-sm/5">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" name="remember" class="size-4 border-transparent shadow-sm ring-1 ring-black/10">
                        Remember me
                    </label>
                </div>

                <div class="mt-8">
                    <x-marketing.button type="submit" class="w-full">Sign in</x-marketing.button>
                </div>
            </form>
        </div>
    </div>
</main>
@endsection
