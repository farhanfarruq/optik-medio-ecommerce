<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [
            [
                'name' => 'Bank Central Asia (BCA)',
                'code' => '014',
                'account_name' => 'PT Optik Medio Indonesia',
                'account_number' => '1234567890',
                'is_active' => true,
            ],
            [
                'name' => 'Bank Mandiri',
                'code' => '008',
                'account_name' => 'PT Optik Medio Indonesia',
                'account_number' => '0987654321',
                'is_active' => true,
            ],
            [
                'name' => 'Bank Negara Indonesia (BNI)',
                'code' => '009',
                'account_name' => 'PT Optik Medio Indonesia',
                'account_number' => '1122334455',
                'is_active' => true,
            ],
            [
                'name' => 'Bank Rakyat Indonesia (BRI)',
                'code' => '002',
                'account_name' => 'PT Optik Medio Indonesia',
                'account_number' => '5544332211',
                'is_active' => false, // Set one to inactive for testing
            ],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['code' => $bank['code']],
                $bank
            );
        }
    }
}
