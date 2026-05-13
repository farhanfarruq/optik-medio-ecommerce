<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expand the orders.status enum to include optical workflow statuses
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM(
            'unpaid',
            'paid',
            'waiting_prescription_review',
            'prescription_verified',
            'lens_processing',
            'processing',
            'shipped',
            'delivered',
            'completed',
            'cancelled',
            'refunded'
        ) NOT NULL DEFAULT 'unpaid'");
    }

    public function down(): void
    {
        // Revert to original enum (data with new statuses will be lost)
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM(
            'unpaid',
            'paid',
            'processing',
            'shipped',
            'delivered',
            'cancelled',
            'refunded'
        ) NOT NULL DEFAULT 'unpaid'");
    }
};
