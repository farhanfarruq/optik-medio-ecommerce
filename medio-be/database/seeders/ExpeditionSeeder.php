<?php

namespace Database\Seeders;

use App\Models\Expedition;
use Illuminate\Database\Seeder;

class ExpeditionSeeder extends Seeder
{
    public function run(): void
    {
        $expeditions = [
            ['name' => 'JNE', 'code' => 'jne', 'is_active' => true, 'sort_order' => 1],
            ['name' => 'J&T Express', 'code' => 'jnt', 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Sicepat', 'code' => 'sicepat', 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Pos Indonesia', 'code' => 'pos', 'is_active' => true, 'sort_order' => 4],
            ['name' => 'TIKI', 'code' => 'tiki', 'is_active' => true, 'sort_order' => 5],
            ['name' => 'AnterAja', 'code' => 'anteraja', 'is_active' => true, 'sort_order' => 6],
            ['name' => 'Ninja Xpress', 'code' => 'ninja', 'is_active' => true, 'sort_order' => 7],
            ['name' => 'Lion Parcel', 'code' => 'lion', 'is_active' => true, 'sort_order' => 8],
            ['name' => 'ID Express', 'code' => 'ide', 'is_active' => true, 'sort_order' => 9],
            ['name' => 'Kargo', 'code' => 'kargo', 'is_active' => false, 'sort_order' => 10], // Default mati (kemahalan)
        ];

        foreach ($expeditions as $expedition) {
            Expedition::updateOrCreate(
                ['code' => $expedition['code']],
                $expedition
            );
        }
    }
}
