@extends('layouts.app')

@section('title', 'Website forms')
@section('breadcrumb', 'Website / Forms')

@section('content')
    <x-page-header title="Forms" kicker="Website" />

    @include('site._tabs')

    <x-card title="Not built yet" class="max-w-3xl">
        <p class="text-body">Contact and quote forms are not built yet: the site cannot take form submissions, so there is nothing to edit here.</p>
        <p class="mt-3 text-body text-ink/70">They arrive with lead intake, which turns a submission into a lead on your board with spam protection. Until then, visitors reach you through the phone number and email under Facts.</p>
        <div class="mt-4">
            <x-button variant="secondary" :href="route('site.facts.edit')">Edit phone and email</x-button>
        </div>
    </x-card>
@endsection
