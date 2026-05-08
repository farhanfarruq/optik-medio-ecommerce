<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function xendit(Request $request): JsonResponse
    {
        $callbackToken = $request->header('x-callback-token');
        $expectedToken = config('services.xendit.webhook_token');

        if (!$expectedToken || $callbackToken !== $expectedToken) {
            Log::warning('Invalid Xendit Webhook Token', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $payload = $request->all();
        $orderNumber = $payload['external_id'] ?? null;
        $status = $payload['status'] ?? null;

        Log::info('Xendit Webhook Received', ['order_number' => $orderNumber, 'status' => $status]);

        if (!$orderNumber || !$status) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $payment = Payment::where('transaction_id', $orderNumber)->first();

        if (!$payment) {
            Log::warning('Payment not found for Xendit Webhook', ['order_number' => $orderNumber]);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $order = $payment->order;

        [$paymentStatus, $orderStatus, $paidAt] = $this->resolveStatus($status, $order->status);

        $wasAlreadyPaid = in_array(strtolower($order->status), ['paid', 'processing', 'shipped', 'delivered']);

        $payment->update([
            'payment_type'   => $payload['payment_channel'] ?? null,
            'payment_method' => $payload['payment_method'] ?? null,
            'status'         => $paymentStatus,
            'raw_response'   => $payload,
            'paid_at'        => $paidAt,
        ]);

        $order->update([
            'status' => $orderStatus,
            'paid_at' => $paidAt,
            'is_payment_verified' => $paymentStatus === 'success',
            'payment_verified_at' => $paymentStatus === 'success' ? $paidAt : null,
        ]);

        // Kirim email konfirmasi saat pertama kali payment berhasil
        if ($paymentStatus === 'success' && !$wasAlreadyPaid) {
            try {
                $order->load('user');
                \Illuminate\Support\Facades\Mail::to($order->user->email)
                    ->send(new \App\Mail\OrderConfirmedMail($order->load(['items.product', 'payment'])));
                Log::info('Order confirmation email sent to ' . $order->user->email);
            } catch (\Exception $e) {
                Log::error('Failed to send order confirmation email: ' . $e->getMessage());
            }
        }

        Log::info('Xendit Webhook Processed', [
            'order_number'   => $orderNumber,
            'payment_status' => $paymentStatus,
            'order_status'   => $orderStatus,
        ]);

        return response()->json(['message' => 'OK']);
    }

    private function resolveStatus(string $xenditStatus, string $currentOrderStatus): array
    {
        if ($xenditStatus === 'PAID' || $xenditStatus === 'SETTLED') {
            return ['success', 'paid', now()];
        }

        if ($xenditStatus === 'EXPIRED') {
            return ['expired', 'cancelled', null];
        }

        if ($xenditStatus === 'FAILED') {
            return ['failed', 'cancelled', null];
        }

        return ['pending', $currentOrderStatus, null];
    }
}
