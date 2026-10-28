@extends('layouts.app')

@section('title', $customer->name)
@section('breadcrumb', 'Customers / '.$customer->name)

@section('content')
    <x-page-header title="{{ $customer->name }}" kicker="People">
        <x-slot:actions>
            {!! app(\App\Core\Plugins\Registry::class)->renderAttachments('customers', $customer) !!}
            <x-button href="{{ route('customers.edit', $customer) }}">Edit</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Details">
            <dl class="space-y-3 text-body">
                <div>
                    <dt class="text-label text-ink/70">Phone</dt>
                    <dd>{{ $customer->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Email</dt>
                    <dd>{{ $customer->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Address</dt>
                    <dd>{{ $customer->address ?: 'No address on file' }}</dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Managing / bill-to</dt>
                    <dd>{{ $customer->billing_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Primary contact</dt>
                    <dd>
                        {{ $customer->primary_contact_name ?: '—' }}
                        @if ($customer->primary_contact_email)
                            <span class="block text-control text-ink/70">{{ $customer->primary_contact_email }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Rate / terms</dt>
                    <dd>
                        {{ $customer->visitRateLabel() ?: '—' }}
                        @if ($customer->payment_terms)
                            <span class="block text-control text-ink/70">{{ $customer->payment_terms }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Notes</dt>
                    <dd>{{ $customer->notes ?: '—' }}</dd>
                </div>
            </dl>
        </x-card>
        @foreach (app(\App\Core\Packages\Hooks::class)->customerPanels($customer) as $panel)
            @include($panel['view'], $panel['data'])
        @endforeach
    </div>

    <x-card title="Client requests" class="mt-6">
        <p class="mb-3 text-control text-ink/70">Schedule or account messages from people with portal access to this site.</p>
        @forelse ($customer->changeRequests as $changeRequest)
            <div class="border-b border-line-soft py-3 last:border-0">
                <div class="text-micro uppercase text-ink/45">{{ $changeRequest->user?->name ?? 'Portal client' }} · {{ $changeRequest->created_at->diffForHumans() }}</div>
                <p class="mt-1 text-body whitespace-pre-wrap">{{ $changeRequest->message }}</p>
            </div>
        @empty
            <p class="text-control text-ink/55">No requests yet.</p>
        @endforelse
    </x-card>

    <x-card title="Client portal access" class="mt-6">
        <p class="mb-3 text-control text-ink/70">People who can sign in to the client portal for this site. New people get an email with a set-password link.</p>
        @if (session('portal_invite_link'))
            <div class="mb-3 border border-line bg-surface p-3 text-control">
                <p class="text-ink/70">Email is not set up on this install (MAIL_MAILER is {{ config('mail.default') }}), so the invite was not delivered. Send this set-password link yourself:</p>
                <input type="text" readonly value="{{ session('portal_invite_link') }}" class="mt-2 w-full border border-line bg-white px-2 py-1 font-mono text-xs">
            </div>
        @endif
        <ul class="mb-4 divide-y divide-line-soft">
            @forelse ($customer->portalUsers as $portalUser)
                <li class="flex items-center justify-between py-2 text-control">
                    <span>{{ $portalUser->name }} · {{ $portalUser->email }}</span>
                    @can('users.manage')
                        <form method="POST" action="{{ route('customers.portal-users.destroy', [$customer, $portalUser]) }}">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="secondary" size="sm">Remove</x-button>
                        </form>
                    @endcan
                </li>
            @empty
                <li class="py-2 text-control text-ink/55">No portal users yet.</li>
            @endforelse
        </ul>
        @can('users.manage')
            <form method="POST" action="{{ route('customers.portal-users.store', $customer) }}" class="grid gap-3 sm:grid-cols-3">
                @csrf
                <x-field label="Name" name="name">
                    <x-input name="name" value="{{ old('name') }}" />
                </x-field>
                <x-field label="Email" name="email" :error="$errors->first('email')">
                    <x-input type="email" name="email" value="{{ old('email') }}" required />
                </x-field>
                <div class="flex items-end">
                    <x-button type="submit">Grant portal access</x-button>
                </div>
            </form>
        @endcan
    </x-card>
@endsection
