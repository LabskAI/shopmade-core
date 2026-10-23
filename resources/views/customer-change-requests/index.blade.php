@extends('layouts.app')

@section('title', 'Client requests')
@section('breadcrumb', 'Client requests')

@section('content')
    <x-page-header title="Client requests" kicker="People" />

    <div class="border-2 border-line bg-surface">
        <x-toolbar title="Client requests" :count="$requests->total()" />
        <x-table :columns="[['label' => 'When'], ['label' => 'Site'], ['label' => 'From'], ['label' => 'Message']]">
            @foreach ($requests as $changeRequest)
                <tr class="border-b border-line-soft">
                    <td class="px-4 py-3 text-control text-ink/70">{{ $changeRequest->created_at->format('M j, Y g:i a') }}</td>
                    <td class="px-4 py-3 text-control">
                        @if ($changeRequest->customer)
                            <a href="{{ route('customers.show', $changeRequest->customer) }}">{{ $changeRequest->customer->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 text-control text-ink/70">{{ $changeRequest->user?->name ?? 'Portal client' }}</td>
                    <td class="px-4 py-3 text-control">{{ \Illuminate\Support\Str::limit($changeRequest->message, 120) }}</td>
                </tr>
            @endforeach
            <x-slot:empty>
                <x-empty title="No client requests yet" body="Portal clients can send schedule or account requests from Home." />
            </x-slot:empty>
        </x-table>
        {{ $requests->links() }}
    </div>
@endsection
