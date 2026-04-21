<?php

namespace App\Services;

use App\Models\Order;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceItem;
use Xendit\Invoice\CustomerObject;

class XenditService
{
    private InvoiceApi $apiInstance;

    public function __construct()
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));
        $this->apiInstance = new InvoiceApi();
    }

    public function createInvoice(Order $order): string
    {
        $createInvoiceRequest = new CreateInvoiceRequest([
            'external_id'      => $order->order_number,
            'amount'           => (float) $order->total_price,
            'payer_email'      => $order->user->email,
            'description'      => 'Invoice for Order ' . $order->order_number,
            'customer'         => new CustomerObject([
                'given_names'   => $order->user->name,
                'email'         => $order->user->email,
                'mobile_number' => $order->shippingAddress->phone,
            ]),
            'success_redirect_url' => url('/dashboard'),
            'failure_redirect_url' => url('/dashboard'),
            'currency'         => 'IDR',
            'items'            => $this->buildItemDetails($order),
        ]);

        $result = $this->apiInstance->createInvoice($createInvoiceRequest);

        return $result->getInvoiceUrl();
    }

    private function buildItemDetails(Order $order): array
    {
        $items = $order->items->map(function ($item) {
            return new InvoiceItem([
                'name'     => substr($item->product_name, 0, 50),
                'price'    => (float) $item->product_price,
                'quantity' => $item->quantity,
            ]);
        })->toArray();

        $items[] = new InvoiceItem([
            'name'     => 'Ongkos Kirim ' . strtoupper($order->courier),
            'price'    => (float) $order->shipping_cost,
            'quantity' => 1,
        ]);

        return $items;
    }
}
