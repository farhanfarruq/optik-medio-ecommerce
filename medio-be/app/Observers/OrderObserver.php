<?php

namespace App\Observers;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderObserver
{
    public function updated(Order $order): void
    {
        // Notifikasi berdasarkan perubahan status
        if ($order->wasChanged('status')) {
            $notifiableStatuses = ['processing', 'cancelled', 'delivered'];
            if (in_array($order->status, $notifiableStatuses)) {
                $this->sendStatusEmail($order, $order->status);
            }
        }

        // Notifikasi saat pembayaran diverifikasi
        if ($order->wasChanged('is_payment_verified') && $order->is_payment_verified) {
            $this->sendStatusEmail($order, 'payment_verified');
        }
    }

    private function sendStatusEmail(Order $order, string $eventType): void
    {
        try {
            $order->loadMissing('user');
            Mail::to($order->user->email)->send(new OrderStatusMail($order, $eventType));
            Log::info("Order status email ({$eventType}) sent to {$order->user->email} for order #{$order->order_number}");
        } catch (\Exception $e) {
            Log::error("Failed to send order status email ({$eventType}) for order #{$order->order_number}: " . $e->getMessage());
        }
    }
}
