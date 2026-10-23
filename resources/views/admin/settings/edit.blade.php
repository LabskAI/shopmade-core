@extends('layouts.app')

@section('title', 'Settings')
@section('breadcrumb', 'Admin / Settings')

@section('content')
    <x-page-header title="Settings" kicker="Admin" />

    <div class="grid max-w-5xl gap-6">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <x-card title="Organization">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Organization name" for="org_name" :error="$errors->first('org_name')">
                        <x-input id="org_name" name="org_name" value="{{ old('org_name', $orgName) }}" required :error="$errors->has('org_name')" />
                    </x-field>
                    <x-field label="Logo path" for="org_logo" :error="$errors->first('org_logo')" hint="A path under public/, e.g. /images/logo.svg">
                        <x-input id="org_logo" name="org_logo" value="{{ old('org_logo', $orgLogo) }}" :error="$errors->has('org_logo')" />
                    </x-field>
                </div>
                <div class="mt-6 flex justify-start gap-2">
                    <x-button type="submit">Save settings</x-button>
                </div>
            </x-card>
        </form>

    </div>
@endsection
