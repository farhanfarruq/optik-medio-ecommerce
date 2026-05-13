<?php

namespace App\Jobs;

use App\Models\Cart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCartReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan ulang jika job gagal.
     */
    public int $tries = 3;

    /**
     * Delay antar retry (detik).
     */
    public int $backoff = 60;

    /**
     * Jalankan job: cari cart abandoned dan kirim reminder.
     * Cart dianggap abandoned jika:
     * - status = active
     * - user_id tidak null (hanya untuk user terdaftar)
     * - last_activity_at > 1 jam yang lalu
     * - abandoned_reminder_sent_at null (belum pernah dikirim)
     * - cart punya minimal 1 item
     */
    public function handle(): void
    {
        $threshold = now()->subHour();

        $carts = Cart::query()
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->where('last_activity_at', '<', $threshold)
            ->whereNull('abandoned_reminder_sent_at')
            ->with(['user', 'items.product.images'])
            ->get()
            ->filter(fn (Cart $cart) => $cart->items->isNotEmpty());

        foreach ($carts as $cart) {
            try {
                $this->sendReminder($cart);

                $cart->update([
                    'status'                      => 'abandoned',
                    'abandoned_reminder_sent_at'  => now(),
                ]);

                Log::info('Abandoned cart reminder sent', [
                    'cart_id' => $cart->id,
                    'user_id' => $cart->user_id,
                    'items'   => $cart->items->count(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Kirim email reminder ke user.
     * Menggunakan Mail::raw sebagai fallback sederhana.
     * Ganti dengan Mailable class jika template email sudah tersedia.
     */
    private function sendReminder(Cart $cart): void
    {
        $user = $cart->user;
        if (! $user || ! $user->email) {
            return;
        }

        $itemCount  = $cart->items->sum('quantity');
        $totalValue = $cart->items->sum(fn ($item) => ($item->product?->price ?? 0) * $item->quantity);
        $firstName  = explode(' ', $user->name)[0];

        $subject = "Hai {$firstName}, kamu meninggalkan {$itemCount} item di keranjang!";

        $body = "Halo {$user->name},\n\n"
            . "Kamu meninggalkan {$itemCount} item senilai Rp " . number_format($totalValue, 0, ',', '.') . " di keranjang Optik Medio.\n\n"
            . "Segera selesaikan pesananmu sebelum stok habis!\n\n"
            . "Kunjungi: " . config('app.frontend_url', config('app.url')) . "/cart\n\n"
            . "Salam,\nTim Optik Medio";

        Mail::raw($body, function ($message) use ($user, $subject) {
            $message->to($user->email, $user->name)
                ->subject($subject)
                ->from(
                    config('mail.from.address', 'noreply@optikmedio.com'),
                    config('mail.from.name', 'Optik Medio')
                );
        });
    }
}
