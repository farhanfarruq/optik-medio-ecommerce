<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_best_seller');
            }

            if (!Schema::hasColumn('products', 'recommendation_priority')) {
                $table->unsignedSmallInteger('recommendation_priority')->default(0)->after('is_featured');
            }

            if (!Schema::hasColumn('products', 'campaign_tags')) {
                $table->json('campaign_tags')->nullable()->after('tags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumnIfExists('campaign_tags');
            $table->dropColumnIfExists('recommendation_priority');
            $table->dropColumnIfExists('is_featured');
        });
    }
};
