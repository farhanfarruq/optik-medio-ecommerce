<?php

namespace App\Mail;

use App\Models\Complain;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewComplainMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Complain $complain) {}

    public function envelope(): Envelope
    {
        $type = $this->complain->complaint_type === 'shipping_protection'
            ? 'Klaim Proteksi Pengiriman'
            : 'Komplain Umum';

        return new Envelope(
            subject: "[Admin] Komplain Baru — {$type} dari {$this->complain->user->name}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin_new_complain');
    }
}
