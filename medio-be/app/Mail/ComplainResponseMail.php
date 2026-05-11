<?php

namespace App\Mail;

use App\Models\Complain;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplainResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Complain $complain,
    ) {}

    public function envelope(): Envelope
    {
        $statusLabel = match ($this->complain->status) {
            'in_progress' => 'Sedang Diproses',
            'resolved'    => 'Telah Diselesaikan',
            'rejected'    => 'Tidak Dapat Diproses',
            default       => 'Diperbarui',
        };

        return new Envelope(
            subject: "[Komplain #{$this->complain->id}] {$statusLabel} — Optik Medio",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.complain_response',
        );
    }
}
