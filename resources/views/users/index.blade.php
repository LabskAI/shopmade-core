@extends('layouts.app')

@section('title', 'Users')
@section('breadcrumb', 'Users')

@section('content')
    <x-page-header title="Users" kicker="People">
        <x-slot:actions>
            <x-button href="{{ route('users.create') }}">Add user</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="border-2 border-line bg-surface">
        <x-toolbar title="Users" :count="$users->count()" />
        <x-table :columns="[['label' => 'Name'], ['label' => 'Email'], ['label' => 'Role'], ['label' => 'Status']]">
            @foreach ($users as $user)
                <tr class="border-b border-line-soft">
                    <td class="px-4 py-3 text-control">
                        @if (auth()->user()->isSuperAdmin() || ! ($user->isSuperAdmin() || $user->isAdmin()))
                            <a href="{{ route('users.edit', $user) }}" class="hover:underline">{{ $user->name }}</a>
                        @else
                            {{ $user->name }}
                        @endif
                    </td>
                    <td class="px-4 py-3 text-control text-ink/70">{{ $user->email }}</td>
                    <td class="px-4 py-3 text-control text-ink/70">{{ $user->role->label() }}</td>
                    <td class="px-4 py-3"><x-status :state="$user->isDeactivated() ? 'disabled' : 'active'" /></td>
                </tr>
            @endforeach
        </x-table>
    </div>
@endsection
