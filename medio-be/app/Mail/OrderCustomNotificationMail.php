<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCustomNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $customMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pesan dari Optik Medio - #{$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order_custom_notification');
    }
}
