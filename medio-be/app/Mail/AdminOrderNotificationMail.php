<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminOrderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $eventType = 'new_order',
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->eventType) {
            'new_order'           => "[Admin] Order Baru #{$this->order->order_number}",
            'payment_proof'       => "[Admin] Bukti Transfer Menunggu Verifikasi — #{$this->order->order_number}",
            'new_complain'        => "[Admin] Komplain Baru dari {$this->order->user->name}",
            default               => "[Admin] Update Order #{$this->order->order_number}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin_order_notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
