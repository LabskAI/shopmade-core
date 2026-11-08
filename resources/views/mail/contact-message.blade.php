<x-mail::message>
# New website message

**{{ $contactMessage->name }}** ({{ $contactMessage->email }}{{ $contactMessage->phone ? ', '.$contactMessage->phone : '' }}) wrote through the {{ $siteName }} contact form:

<x-mail::panel>
{{ $contactMessage->message }}
</x-mail::panel>

Reply to this email to answer them.

<x-mail::button :url="route('contact-messages.index')">
Open messages
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
