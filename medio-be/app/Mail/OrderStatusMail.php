<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $eventType, // 'payment_verified' | 'processing' | 'cancelled' | 'delivered'
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->eventType) {
            'payment_verified' => "✅ Pembayaran Pesanan #{$this->order->order_number} Terverifikasi — Optik Medio",
            'processing'       => "🔄 Pesanan #{$this->order->order_number} Sedang Diproses — Optik Medio",
            'cancelled'        => "❌ Pesanan #{$this->order->order_number} Dibatalkan — Optik Medio",
            'delivered'        => "📦 Pesanan #{$this->order->order_number} Telah Diterima — Optik Medio",
            default            => "Update Pesanan #{$this->order->order_number} — Optik Medio",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order_status');
    }
}
