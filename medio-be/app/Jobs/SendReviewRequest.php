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

    public function handle(): void
    {
        $cutoff = now()->subDays(3);

        $orders = Order::with(['user', 'items'])
            ->where('status', 'delivered')
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($inner) use ($cutoff) {
                    // Order yang punya delivered_at dan sudah lewat cutoff
                    $inner->whereNotNull('delivered_at')
                          ->where('delivered_at', '<=', $cutoff);
                })->orWhere(function ($inner) use ($cutoff) {
                    // Order lama yang delivered_at-nya null, pakai updated_at sebagai fallback
                    $inner->whereNull('delivered_at')
                          ->where('updated_at', '<=', $cutoff);
                });
            })
            ->whereDoesntHave('returnRequest', fn ($query) => $query
                ->whereIn('status', ['pending', 'approved']))
            ->whereDoesntHave('complains', fn ($query) => $query
                ->whereIn('status', ['open', 'in_progress']))
            ->get();

        foreach ($orders as $order) {
            try {
                $updateData = ['status' => 'completed'];

                // Isi delivered_at jika masih null (pakai updated_at sebagai estimasi waktu delivered)
                if ($order->delivered_at === null) {
                    $updateData['delivered_at'] = $order->updated_at;
                }

                $order->update($updateData);

                $email = $order->user?->email;
                if ($email && $order->items->isNotEmpty() && $order->review_requested_at === null) {
                    Mail::to($email, $order->user->name)
                        ->send(new ReviewRequestMail($order));

                    $order->update(['review_requested_at' => now()]);
                }

                Log::info('Delivered order auto-completed', [
                    'order_id' => $order->id,
                    'email'    => $email,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to auto-complete delivered order', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }
}
