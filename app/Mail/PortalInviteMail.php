<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalInviteMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public User $user,
        public Customer $customer,
        public string $setPasswordUrl,
        public int $expiresInHours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Set your password for the '.config('app.name').' client portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.portal-invite',
        );
    }
}
