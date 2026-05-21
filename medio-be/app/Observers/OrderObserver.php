<?php

namespace App\Observers;

use App\Http\Controllers\API\ReferralController;
use App\Mail\AdminOrderNotificationMail;
use App\Mail\OrderStatusMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OBS-3 (Phase 6): Standardized log format — semua Log::* pakai array context
 * (bukan string concat). Lebih mudah di-parse oleh log aggregator (CloudWatch,
 * Datadog, ELK), dan exception object di-include sebagai field terstruktur.
 */
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
            Log::info('Admin notified: new order', [
                'order_number' => $order->order_number,
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify admin for new order', [
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
            ]);
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
                Log::info('Admin notified: payment proof uploaded', [
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to notify admin for payment proof', [
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'exception' => $e->getMessage(),
                ]);
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
                    Log::error('Failed to reward referral', [
                        'order_number' => $order->order_number,
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'exception' => $e->getMessage(),
                    ]);
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
            Log::info('Order status email sent', [
                'event_type' => $eventType,
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'recipient' => $order->user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send order status email', [
                'event_type' => $eventType,
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
