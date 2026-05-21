<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replacements = [
            'categories/seed/kacamata-frame.svg' => 'categories/seed/frame-unisex.png',
            'categories/seed/lensa-kontak.svg' => 'categories/seed/softlens.png',
            'categories/seed/sunglasses.svg' => 'categories/seed/kacamata-hitam.png',
        ];

        foreach ($replacements as $from => $to) {
            DB::table('categories')
                ->where('image', $from)
                ->update(['image' => $to]);
        }
    }

    public function down(): void
    {
        $replacements = [
            'categories/seed/frame-unisex.png' => 'categories/seed/kacamata-frame.svg',
            'categories/seed/softlens.png' => 'categories/seed/lensa-kontak.svg',
            'categories/seed/kacamata-hitam.png' => 'categories/seed/sunglasses.svg',
        ];

        foreach ($replacements as $from => $to) {
            DB::table('categories')
                ->where('image', $from)
                ->update(['image' => $to]);
        }
    }
};
