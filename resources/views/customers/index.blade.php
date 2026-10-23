@extends('layouts.app')

@section('title', 'Customers')
@section('breadcrumb', 'Customers')

@section('content')
    <x-page-header title="Customers" kicker="People">
        <x-slot:actions>
            <x-button href="{{ route('customers.create') }}"><i data-lucide="plus" class="size-4"></i>New customer</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="border-2 border-line bg-surface">
        <form method="GET">
            <x-toolbar title="Customers" :count="$customers->total()">
                <x-slot:right>
                    <x-input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email, phone, bill to" />
                </x-slot:right>
            </x-toolbar>
        </form>
        <x-table :columns="[['label' => 'Name'], ['label' => 'Bill to'], ['label' => 'Phone'], ['label' => 'Email'], ['label' => 'Address']]">
            @foreach ($customers as $customer)
                <tr class="border-b border-line-soft">
                    <td class="px-4 py-3 text-control"><a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                    <td class="px-4 py-3 text-control text-ink/70">{{ $customer->billing_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-control text-ink/70">{{ $customer->phone ?: '—' }}</td>
                    <td class="px-4 py-3 text-control text-ink/70">{{ $customer->email ?: '—' }}</td>
                    <td class="px-4 py-3 text-control text-ink/70">{{ $customer->address ?: '—' }}</td>
                </tr>
            @endforeach
            <x-slot:empty>
                <x-empty title="No customers yet" body="Add your first customer to give them portal access and track their requests.">
                    <x-slot:action>
                        <x-button variant="secondary" href="{{ route('customers.create') }}">New customer</x-button>
                    </x-slot:action>
                </x-empty>
            </x-slot:empty>
        </x-table>
        {{ $customers->links() }}
    </div>
@endsection
