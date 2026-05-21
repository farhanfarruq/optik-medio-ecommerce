<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'gender')) {
                $table->string('gender')->nullable()->after('brand');
            }

            if (! Schema::hasColumn('products', 'frame_shape')) {
                $table->string('frame_shape')->nullable()->after('gender');
            }

            if (! Schema::hasColumn('products', 'frame_material')) {
                $table->string('frame_material')->nullable()->after('frame_shape');
            }

            if (! Schema::hasColumn('products', 'frame_color')) {
                $table->string('frame_color')->nullable()->after('frame_material');
            }

            if (! Schema::hasColumn('products', 'face_size_fit')) {
                $table->string('face_size_fit')->nullable()->after('frame_color');
            }

            if (! Schema::hasColumn('products', 'lens_width')) {
                $table->unsignedSmallInteger('lens_width')->nullable()->after('dimensions');
            }

            if (! Schema::hasColumn('products', 'bridge_width')) {
                $table->unsignedSmallInteger('bridge_width')->nullable()->after('lens_width');
            }

            if (! Schema::hasColumn('products', 'temple_length')) {
                $table->unsignedSmallInteger('temple_length')->nullable()->after('bridge_width');
            }

            if (! Schema::hasColumn('products', 'frame_width')) {
                $table->unsignedSmallInteger('frame_width')->nullable()->after('temple_length');
            }

            if (! Schema::hasColumn('products', 'google_product_category')) {
                $table->string('google_product_category')->nullable()->after('tags');
            }

            if (! Schema::hasColumn('products', 'gtin')) {
                $table->string('gtin')->nullable()->after('google_product_category');
            }

            if (! Schema::hasColumn('products', 'mpn')) {
                $table->string('mpn')->nullable()->after('gtin');
            }

            if (! Schema::hasColumn('products', 'condition')) {
                $table->string('condition')->default('new')->after('mpn');
            }

            if (! Schema::hasColumn('products', 'prescription_rules')) {
                $table->json('prescription_rules')->nullable()->after('is_prescription_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumnIfExists('gender');
            $table->dropColumnIfExists('frame_shape');
            $table->dropColumnIfExists('frame_material');
            $table->dropColumnIfExists('frame_color');
            $table->dropColumnIfExists('face_size_fit');
            $table->dropColumnIfExists('lens_width');
            $table->dropColumnIfExists('bridge_width');
            $table->dropColumnIfExists('temple_length');
            $table->dropColumnIfExists('frame_width');
            $table->dropColumnIfExists('google_product_category');
            $table->dropColumnIfExists('gtin');
            $table->dropColumnIfExists('mpn');
            $table->dropColumnIfExists('condition');
            $table->dropColumnIfExists('prescription_rules');
        });
    }
};
