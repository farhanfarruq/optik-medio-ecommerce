<?php

namespace Database\Seeders;

use App\Models\LevelMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LevelMemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            [
                'name' => 'Bronze',
                'min_points' => 0,
                'discount_percentage' => 0,
                'sort_order' => 1,
                'is_active' => true,
                'description' => 'Level awal untuk setiap pelanggan baru. Nikmati akumulasi poin setiap belanja.',
            ],
            [
                'name' => 'Silver',
                'min_points' => 50000, // Akumulasi belanja sekitar Rp 5.000.000 (1% poin)
                'discount_percentage' => 2.5,
                'sort_order' => 2,
                'is_active' => true,
                'description' => 'Nikmati potongan harga langsung 2.5% untuk setiap transaksi tanpa minimum belanja.',
            ],
            [
                'name' => 'Gold',
                'min_points' => 250000, // Akumulasi belanja sekitar Rp 25.000.000
                'discount_percentage' => 5.0,
                'sort_order' => 3,
                'is_active' => true,
                'description' => 'Status VIP. Potongan harga 5% dan prioritas pemrosesan pesanan.',
            ],
            [
                'name' => 'Platinum',
                'min_points' => 1000000, // Akumulasi belanja sekitar Rp 100.000.000
                'discount_percentage' => 10.0,
                'sort_order' => 4,
                'is_active' => true,
                'description' => 'Status tertinggi. Potongan harga 10%, bebas biaya kirim, dan akses eksklusif ke koleksi terbaru.',
            ],
        ];

        foreach ($levels as $level) {
            LevelMember::updateOrCreate(
                ['slug' => Str::slug($level['name'])],
                $level
            );
        }
    }
}
