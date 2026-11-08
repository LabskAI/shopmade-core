@extends('layouts.app')

@section('title', 'Messages')
@section('breadcrumb', 'Messages')

@section('content')
    <x-page-header title="Messages" kicker="People" />

    <div class="border-2 border-line bg-surface">
        <x-toolbar title="Website messages" :count="$messages->total()" />
        <x-table :columns="[['label' => 'When'], ['label' => 'From'], ['label' => 'Message']]">
            @foreach ($messages as $contactMessage)
                <tr class="border-b border-line-soft align-top">
                    <td class="px-4 py-3 text-control text-ink/70 whitespace-nowrap">
                        {{ $contactMessage->created_at->format('M j, Y g:i a') }}
                        @unless ($contactMessage->isRead())
                            <span class="ml-1 font-semibold text-ink">New</span>
                        @endunless
                    </td>
                    <td class="px-4 py-3 text-control">
                        <div class="font-medium">{{ $contactMessage->name }}</div>
                        <div><a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a></div>
                        @if ($contactMessage->phone)
                            <div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactMessage->phone) }}">{{ $contactMessage->phone }}</a></div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-control">
                        <div class="whitespace-pre-line">{{ $contactMessage->message }}</div>
                        @if ($contactMessage->page_url)
                            <div class="mt-1 text-ink/60">From {{ $contactMessage->page_url }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
            <x-slot:empty>
                <x-empty title="No messages yet" body="Messages sent through the website contact form show up here." />
            </x-slot:empty>
        </x-table>
        {{ $messages->links() }}
    </div>
@endsection
