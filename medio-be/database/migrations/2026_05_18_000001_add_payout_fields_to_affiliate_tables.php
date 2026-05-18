<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_affiliators', function (Blueprint $table) {
            $table->string('payout_method')->nullable();
            $table->string('payout_bank_name')->nullable();
            $table->string('payout_account_number')->nullable();
            $table->string('payout_account_name')->nullable();
            $table->text('payout_notes')->nullable();
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->string('payout_method')->nullable();
            $table->string('payout_bank_name')->nullable();
            $table->string('payout_account_number')->nullable();
            $table->string('payout_account_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn([
                'payout_method',
                'payout_bank_name',
                'payout_account_number',
                'payout_account_name',
            ]);
        });

        Schema::table('user_affiliators', function (Blueprint $table) {
            $table->dropColumn([
                'payout_method',
                'payout_bank_name',
                'payout_account_number',
                'payout_account_name',
                'payout_notes',
            ]);
        });
    }
};

