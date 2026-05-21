<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('shipping_protection_opted')
                ->default(false)
                ->after('shipping_cost');
            $table->decimal('shipping_protection_fee', 15, 2)
                ->default(0)
                ->after('shipping_protection_opted');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_protection_opted',
                'shipping_protection_fee',
            ]);
        });
    }
};
