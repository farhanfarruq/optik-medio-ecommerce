<?php

namespace App\Mail;

use App\Models\ServiceClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewServiceClaimMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ServiceClaim $claim) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Admin] Klaim Servis Baru — #{$this->claim->claim_number} dari {$this->claim->user->name}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin_new_service_claim');
    }
}
