<?php

namespace App\Jobs;

use App\Mail\ReviewRequestMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReviewRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 120;

    /**
     * Kirim email request review untuk order yang delivered 3 hari lalu
     * dan belum pernah dikirim review request.
     */
    public function handle(): void
    {
        $targetDate = now()->subDays(3)->toDateString();

        $orders = Order::with(['user', 'items'])
            ->where('status', 'delivered')
            ->whereDate('delivered_at', $targetDate)
            ->whereNull('review_requested_at')
            ->get();

        foreach ($orders as $order) {
            try {
                $email = $order->user?->email;
                if (! $email || $order->items->isEmpty()) {
                    continue;
                }

                Mail::to($email, $order->user->name)
                    ->send(new ReviewRequestMail($order));

                $order->update(['review_requested_at' => now()]);

                Log::info('Review request sent', [
                    'order_id' => $order->id,
                    'email'    => $email,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send review request', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }
}
