<x-mail::message>
# Set your client portal password

Hello {{ $user->name }},

You have been invited to the {{ config('app.org_name', config('app.name')) }} client portal for **{{ $customer->name }}**.

Use the button below to choose your password. This link expires in {{ $expiresInHours }} hours and can be used once.

<x-mail::button :url="$setPasswordUrl">
Set your password
</x-mail::button>

If you did not expect this email, you can ignore it.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
