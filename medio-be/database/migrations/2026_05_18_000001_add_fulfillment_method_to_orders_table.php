<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom fulfillment_method ke tabel orders dan buat shipping_address_id nullable.
     *
     * fulfillment_method:
     *   - 'delivery'      : dikirim ke alamat (default, perilaku lama)
     *   - 'store_pickup'  : pembeli ambil sendiri di toko, ongkir = 0
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Buat shipping_address_id nullable agar store_pickup tidak wajib isi alamat
            $table->foreignId('shipping_address_id')->nullable()->change();

            // Kolom metode pemenuhan pesanan
            $table->string('fulfillment_method')->default('delivery')->after('shipping_address_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_method');

            // Kembalikan ke NOT NULL (hati-hati jika ada data null)
            $table->foreignId('shipping_address_id')->nullable(false)->change();
        });
    }
};
