<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complains', function (Blueprint $table) {
            $table->string('complaint_type')
                ->default('general')
                ->after('order_id');

            $table->index(['order_id', 'complaint_type', 'status'], 'complains_order_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('complains', function (Blueprint $table) {
            $table->dropIndex('complains_order_type_status_idx');
            $table->dropColumn('complaint_type');
        });
    }
};
