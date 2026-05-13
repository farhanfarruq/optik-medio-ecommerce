<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookEventLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmedMail;

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

        $payload = $request->validate([
            'external_id' => 'required|string|max:255',
            'status' => 'required|string|max:50',
            'payment_channel' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:255',
        ]);
        $orderNumber = $payload['external_id'] ?? null;
        $status = $payload['status'] ?? null;

        Log::info('Xendit Webhook Received', ['order_number' => $orderNumber, 'status' => $status]);

        // Catat webhook event log untuk audit trail dan idempotency tracking
        $eventLog = WebhookEventLog::record('xendit', $orderNumber, $status, $payload);

        $payment = Payment::where('transaction_id', $orderNumber)->first();

        if (!$payment) {
            Log::warning('Payment not found for Xendit Webhook', ['order_number' => $orderNumber]);
            $eventLog->markFailed('Payment record not found for order: ' . $orderNumber);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $order = $payment->order;

        [$paymentStatus, $orderStatus, $paidAt] = $this->resolveStatus($status, $order->status);
        $currentPaymentStatus = strtolower((string) $payment->status);
        $currentOrderStatus = strtolower((string) $order->status);

        if (
            $currentPaymentStatus === strtolower($paymentStatus)
            && $currentOrderStatus === strtolower($orderStatus)
            && (bool) $order->is_payment_verified === ($paymentStatus === 'success')
        ) {
            Log::info('Xendit Webhook Replay Ignored', [
                'order_number' => $orderNumber,
                'payment_status' => $paymentStatus,
                'order_status' => $orderStatus,
            ]);

            $eventLog->markSkipped('Replay ignored: status already matches.');
            return response()->json(['message' => 'OK']);
        }

        $wasAlreadyPaid = in_array(strtolower($order->status), ['paid', 'processing', 'shipped', 'delivered']);

        DB::transaction(function () use ($payment, $order, $payload, $paymentStatus, $orderStatus, $paidAt, $currentOrderStatus): void {
            $payment->forceFill([
                'payment_type'   => $payload['payment_channel'] ?? $payment->payment_type,
                'payment_method' => $payload['payment_method'] ?? $payment->payment_method,
                'status'         => $paymentStatus,
                'raw_response'   => $payload,
                'paid_at'        => $paidAt,
            ])->saveQuietly();

            $order->forceFill([
                'status' => $orderStatus,
                'paid_at' => $paidAt,
                'is_payment_verified' => $paymentStatus === 'success',
                'payment_verified_at' => $paymentStatus === 'success' ? $paidAt : null,
            ])->saveQuietly();

            // ── ORDER-004: Catat order_log manual karena saveQuietly() skip booted() hooks ──
            if ($currentOrderStatus !== strtolower($orderStatus)) {
                \App\Models\OrderLog::create([
                    'order_id'        => $order->id,
                    'event_type'      => 'status_changed',
                    'previous_status' => $currentOrderStatus,
                    'current_status'  => $orderStatus,
                    'title'           => 'Status diperbarui via Xendit',
                    'description'     => sprintf(
                        'Status pesanan berubah dari %s menjadi %s melalui notifikasi Xendit.',
                        $currentOrderStatus,
                        $orderStatus
                    ),
                    'acted_by' => null,
                ]);
            }

            if ($paymentStatus === 'success') {
                \App\Models\OrderLog::create([
                    'order_id'       => $order->id,
                    'event_type'     => 'payment_verified',
                    'current_status' => $orderStatus,
                    'title'          => 'Pembayaran diverifikasi via Xendit',
                    'description'    => 'Pembayaran pesanan telah dikonfirmasi oleh Xendit.',
                    'acted_by'       => null,
                ]);
            }
        });

        if ($paymentStatus === 'success' && !$wasAlreadyPaid) {
            try {
                $order->load('user');
                Mail::to($order->user->email)
                    ->send(new OrderConfirmedMail($order->load(['items.product', 'payment'])));
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

        $eventLog->markProcessed("Order {$orderNumber} updated to {$orderStatus}.");

        // Catat business event payment_success
        if ($paymentStatus === 'success' && ! $wasAlreadyPaid) {
            \App\Models\BusinessEvent::record(
                eventType: \App\Models\BusinessEvent::PAYMENT_SUCCESS,
                payload: [
                    'order_number'   => $orderNumber,
                    'order_id'       => $order->id,
                    'payment_method' => $payload['payment_method'] ?? null,
                    'amount'         => (float) $order->total_price,
                ],
                userId: $order->user_id,
            );
        }

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
