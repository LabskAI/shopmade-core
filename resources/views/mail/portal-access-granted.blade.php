<x-mail::message>
# Client portal access

Hello {{ $user->name }},

You have been given access to **{{ $customer->name }}** in the {{ config('app.org_name', config('app.name')) }} client portal.

Sign in with the password you already use.

<x-mail::button :url="route('login')">
Sign in
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
