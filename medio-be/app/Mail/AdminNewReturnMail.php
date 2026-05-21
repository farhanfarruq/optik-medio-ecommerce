<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewReturnMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReturnRequest $returnRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Admin] Retur Baru — Order #{$this->returnRequest->order?->order_number} dari {$this->returnRequest->user->name}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin_new_return');
    }
}
