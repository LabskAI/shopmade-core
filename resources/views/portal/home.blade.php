@extends('layouts.portal')

@section('title', 'Home')

@section('content')
    <x-page-header title="Hello, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}" kicker="Client portal" />

    @foreach ($customers as $customer)
        <x-card :title="$customer->name" kicker="Site" class="mb-4">
            <dl class="space-y-3 text-body">
                @if ($customer->address)
                    <div>
                        <dt class="text-label text-ink/70">Address</dt>
                        <dd>{{ $customer->address }}</dd>
                    </div>
                @endif
                @if ($customer->billing_name)
                    <div>
                        <dt class="text-label text-ink/70">Managing / bill-to</dt>
                        <dd>{{ $customer->billing_name }}</dd>
                    </div>
                @endif
                @if ($customer->primary_contact_name || $customer->primary_contact_email)
                    <div>
                        <dt class="text-label text-ink/70">Primary contact</dt>
                        <dd>
                            {{ $customer->primary_contact_name ?: '—' }}
                            @if ($customer->primary_contact_email)
                                <span class="block text-control text-ink/70">{{ $customer->primary_contact_email }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if ($customer->visitRateLabel() || $customer->payment_terms)
                    <div>
                        <dt class="text-label text-ink/70">Rate / terms</dt>
                        <dd>
                            {{ $customer->visitRateLabel() ?: '—' }}
                            @if ($customer->payment_terms)
                                <span class="block text-control text-ink/70">{{ $customer->payment_terms }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>

            <form method="POST" action="{{ route('portal.customers.change-requests.store', $customer) }}" class="mt-5 border-t-2 border-line-soft pt-4">
                @csrf
                <x-field label="Request a change" for="message-{{ $customer->id }}" :error="$errors->first('message')" hint="Ask us to change something about your account or this site.">
                    <x-textarea id="message-{{ $customer->id }}" name="message" required rows="3" :error="$errors->has('message')">{{ old('customer_id') == $customer->id ? old('message') : '' }}</x-textarea>
                </x-field>
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <div class="mt-3">
                    <x-button type="submit"><i data-lucide="send" class="size-4"></i>Send request</x-button>
                </div>
            </form>
        </x-card>
    @endforeach

    @foreach ($sections as $section)
        @include($section['view'], $section['data'])
    @endforeach
@endsection
