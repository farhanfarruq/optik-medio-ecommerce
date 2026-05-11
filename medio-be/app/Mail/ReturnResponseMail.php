<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ReturnRequest $returnRequest,
    ) {}

    public function envelope(): Envelope
    {
        $statusLabel = match ($this->returnRequest->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default    => 'Diperbarui',
        };

        return new Envelope(
            subject: "[Return] Pengajuan Return #{$this->returnRequest->id} {$statusLabel} — Optik Medio",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.return_response',
        );
    }
}
