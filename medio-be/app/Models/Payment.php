<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'payment_method_id', 'transaction_id', 'checkout_url', 'provider',
        'payment_type', 'payment_method', 'gross_amount',
        'status', 'raw_response', 'paid_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'raw_response' => 'array',
        'paid_at'      => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function booted(): void
    {
        static::updated(function (self $payment): void {
            if ($payment->wasChanged('status')) {
                $order = $payment->order;
                if ($order) {
                    if ($payment->status === 'success') {
                        if ($order->status === 'unpaid') {
                            $order->update([
                                'status' => 'paid',
                                'is_payment_verified' => true,
                                'payment_verified_at' => now(),
                                'verified_by' => auth()->id(),
                                'paid_at' => now(),
                            ]);
                        }
                    } elseif (in_array($payment->status, ['failed', 'cancelled', 'expired'])) {
                        if (in_array($order->status, ['unpaid', 'paid'])) {
                            $order->update([
                                'status' => 'cancelled'
                            ]);
                        }
                    }
                }
            }
        });
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
