<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalAccessGrantedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public User $user,
        public Customer $customer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have access to '.$this->customer->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.portal-access-granted',
        );
    }
}
