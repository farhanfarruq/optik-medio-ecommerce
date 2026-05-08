<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ReturnRequest $returnRequest,
        public User $customer,
        public Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Return Request] Pesanan #' . $this->order->order_number . ' dari ' . $this->customer->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.return_request',
        );
    }
}
