<?php

namespace App\Observers;

use App\Http\Controllers\API\ReferralController;
use App\Mail\AdminOrderNotificationMail;
use App\Mail\OrderStatusMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderObserver
{
    /** Email admin yang menerima notifikasi. */
    private function adminEmail(): string
    {
        return config('mail.admin_notification_email', config('mail.from.address'));
    }

    public function created(Order $order): void
    {
        // Notifikasi admin: order baru masuk
        try {
            $order->loadMissing(['user', 'items', 'bank']);
            Mail::to($this->adminEmail())
                ->send(new AdminOrderNotificationMail($order, 'new_order'));
            Log::info("Admin notified: new order #{$order->order_number}");
        } catch (\Exception $e) {
            Log::error("Failed to notify admin for new order #{$order->order_number}: " . $e->getMessage());
        }
    }

    public function updated(Order $order): void
    {
        // Notifikasi admin: bukti transfer manual diunggah
        if ($order->wasChanged('payment_proof_image') && filled($order->payment_proof_image)) {
            try {
                $order->loadMissing(['user', 'items', 'bank']);
                Mail::to($this->adminEmail())
                    ->send(new AdminOrderNotificationMail($order, 'payment_proof'));
                Log::info("Admin notified: payment proof uploaded for order #{$order->order_number}");
            } catch (\Exception $e) {
                Log::error("Failed to notify admin for payment proof #{$order->order_number}: " . $e->getMessage());
            }
        }

        // Notifikasi user berdasarkan perubahan status
        if ($order->wasChanged('status')) {
            $notifiableStatuses = ['processing', 'cancelled', 'delivered'];
            if (in_array($order->status, $notifiableStatuses)) {
                $this->sendStatusEmail($order, $order->status);
            }

            if (in_array($order->status, ['delivered', 'completed'], true) && $order->user_id) {
                try {
                    ReferralController::rewardInviterIfEligible($order->user_id);
                } catch (\Exception $e) {
                    Log::error("Failed to reward referral for order #{$order->order_number}: " . $e->getMessage());
                }
            }
        }

        // Notifikasi user saat pembayaran diverifikasi
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
