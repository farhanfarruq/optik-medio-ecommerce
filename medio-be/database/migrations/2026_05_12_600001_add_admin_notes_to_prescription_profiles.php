<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_profiles', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('notes')
                ->comment('Catatan admin saat verifikasi atau penolakan resep');
            $table->string('verification_status')->default('pending')->after('admin_notes');
            // pending, approved, rejected
        });
    }

    public function down(): void
    {
        Schema::table('prescription_profiles', function (Blueprint $table) {
            $table->dropColumn(['admin_notes', 'verification_status']);
        });
    }
};
