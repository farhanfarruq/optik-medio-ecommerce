<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\XenditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(private XenditService $xenditService) {}

    public function create(array $orderData, array $items): Order
    {
        return DB::transaction(function () use ($orderData, $items) {
            /** @var PaymentMethod|null $paymentMethod */
            $paymentMethod = $orderData['payment_method_model'] ?? null;
            unset($orderData['payment_method_model']);

            $orderData['order_number'] = 'ORD-' . strtoupper(Str::random(10));
            $order = Order::create($orderData);

            $createdItems = [];

            foreach ($items as $index => $item) {
                $orderItem = OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['product_id'],
                    'product_name'  => $item['product_name'],
                    'product_price' => $item['product_price'],
                    'quantity'      => $item['quantity'],
                    'weight'        => $item['weight'],
                    'variant'       => $item['variant'] ?? null,
                    'prescription'  => $item['prescription'] ?? null,
                    'subtotal'      => $item['product_price'] * $item['quantity'],
                ]);
                
                $createdItems[$index] = $orderItem;
            }

            foreach ($items as $index => $item) {
                if (isset($item['linked_item_index']) && isset($createdItems[$item['linked_item_index']])) {
                    $createdItems[$index]->update([
                        'parent_item_id' => $createdItems[$item['linked_item_index']]->id,
                    ]);
                }
            }

            $order->load(['items', 'payment', 'shippingAddress', 'user', 'bank']);

            $checkoutUrl = null;
            $paymentProvider = $paymentMethod?->provider ?? 'manual';
            $paymentType = $paymentMethod?->type;
            $paymentMethodCode = $paymentMethod?->code;

            if ($paymentProvider === 'xendit') {
                $checkoutUrl = $this->xenditService->createInvoice($order);
            }

            Payment::create([
                'payment_method_id' => $paymentMethod?->id,
                'order_id'        => $order->id,
                'transaction_id'  => $order->order_number,
                'checkout_url'    => $checkoutUrl,
                'provider'        => $paymentProvider,
                'payment_type'    => $paymentType,
                'payment_method'  => $paymentMethodCode,
                'gross_amount'    => $order->total_price,
            ]);

            return $order->fresh(['items', 'payment.paymentMethod', 'shippingAddress', 'bank', 'logs.actedBy']);
        });
    }

    public function findById(int $id): Order
    {
        return Order::with(['items.product', 'items.review', 'payment.paymentMethod', 'shippingAddress', 'user', 'returnRequest', 'bank', 'logs.actedBy'])->findOrFail($id);
    }

    public function findByOrderNumber(string $orderNumber): Order
    {
        return Order::with(['items.product', 'payment.paymentMethod', 'shippingAddress', 'bank', 'logs.actedBy'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $order = Order::findOrFail($id);

        return $order->update(['status' => $status]);
    }

    public function getUserOrders(int $userId)
    {
        return Order::with(['items.product', 'payment.paymentMethod', 'bank'])
            ->where('user_id', $userId)
            ->latest()
            ->paginate(10);
    }
}
