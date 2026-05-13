<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('code', 20)->unique();
            $table->integer('total_uses')->default(0);
            $table->integer('reward_inviter')->default(0);  // poin untuk yang mengundang
            $table->integer('reward_invitee')->default(0);  // poin untuk yang diundang
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['code', 'is_active']);
        });

        Schema::create('referral_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->constrained()->onDelete('cascade');
            $table->foreignId('inviter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('invitee_id')->constrained('users')->onDelete('cascade');
            $table->boolean('inviter_rewarded')->default(false);
            $table->boolean('invitee_rewarded')->default(false);
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->unique(['referral_code_id', 'invitee_id']); // satu user hanya bisa pakai satu kode
            $table->index(['inviter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_uses');
        Schema::dropIfExists('referral_codes');
    }
};
