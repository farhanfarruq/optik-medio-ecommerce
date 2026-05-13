<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('lens_option_id')->nullable()->after('parent_item_id')->constrained('lens_options')->nullOnDelete();
            $table->foreignId('lens_coating_id')->nullable()->after('lens_option_id')->constrained('lens_coatings')->nullOnDelete();
            $table->foreignId('prescription_profile_id')->nullable()->after('lens_coating_id')->constrained('prescription_profiles')->nullOnDelete();
            $table->decimal('lens_price', 15, 2)->default(0)->after('prescription_profile_id');
            $table->decimal('coating_price', 15, 2)->default(0)->after('lens_price');
            $table->json('configuration_snapshot')->nullable()->after('coating_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['lens_option_id']);
            $table->dropForeign(['lens_coating_id']);
            $table->dropForeign(['prescription_profile_id']);
            $table->dropColumn([
                'lens_option_id',
                'lens_coating_id',
                'prescription_profile_id',
                'lens_price',
                'coating_price',
                'configuration_snapshot',
            ]);
        });
    }
};
