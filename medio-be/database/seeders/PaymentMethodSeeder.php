<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Transfer Bank Manual',
                'code' => 'manual_bank_transfer',
                'type' => 'manual_transfer',
                'provider' => 'manual',
                'instructions' => 'Lakukan transfer ke rekening toko yang dipilih, lalu unggah bukti pembayaran pada detail pesanan.',
                'is_active' => true,
                'requires_bank_selection' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pembayaran Online Xendit',
                'code' => 'xendit_invoice',
                'type' => 'gateway',
                'provider' => 'xendit',
                'instructions' => 'Anda akan diarahkan ke halaman pembayaran online untuk menyelesaikan transaksi.',
                'is_active' => true,
                'requires_bank_selection' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Cash On Delivery (Bayar di Tempat)',
                'code' => 'cod',
                'type' => 'cash',
                'provider' => 'manual',
                'instructions' => 'Bayar pesanan Anda secara tunai kepada kurir saat barang tiba.',
                'is_active' => true,
                'requires_bank_selection' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method,
            );
        }
    }
}
