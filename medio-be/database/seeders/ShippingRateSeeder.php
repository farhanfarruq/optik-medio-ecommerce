<?php

namespace Database\Seeders;

use App\Models\Expedition;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingRateSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedRates();
        });

        $this->command?->info('Tarif ongkir internal berhasil diisi.');
    }

    private function seedRates(): void
    {
        $locations = [
            [
                'province' => 'Lampung',
                'province_id' => '18',
                'city' => 'Lampung Tengah',
                'city_id' => '1802',
                'district' => 'Padang Ratu',
                'district_id' => '180210',
                'postal_code' => '34176',
                'zone' => 'local',
            ],
            [
                'province' => 'Lampung',
                'province_id' => '18',
                'city' => 'Bandar Lampung',
                'city_id' => '1871',
                'district' => 'Tanjung Karang Pusat',
                'district_id' => '187104',
                'postal_code' => '35119',
                'zone' => 'lampung',
            ],
            [
                'province' => 'Lampung',
                'province_id' => '18',
                'city' => 'Metro',
                'city_id' => '1872',
                'district' => 'Metro Pusat',
                'district_id' => '187201',
                'postal_code' => '34111',
                'zone' => 'lampung',
            ],
            [
                'province' => 'DKI Jakarta',
                'province_id' => '31',
                'city' => 'Jakarta Pusat',
                'city_id' => '3171',
                'district' => 'Menteng',
                'district_id' => '317103',
                'postal_code' => '10310',
                'zone' => 'java',
            ],
            [
                'province' => 'Jawa Barat',
                'province_id' => '32',
                'city' => 'Bandung',
                'city_id' => '3273',
                'district' => 'Coblong',
                'district_id' => '327304',
                'postal_code' => '40132',
                'zone' => 'java',
            ],
            [
                'province' => 'Jawa Timur',
                'province_id' => '35',
                'city' => 'Surabaya',
                'city_id' => '3578',
                'district' => 'Genteng',
                'district_id' => '357802',
                'postal_code' => '60275',
                'zone' => 'java',
            ],
        ];

        $services = [
            'jne' => [
                ['Regular', 'REG', ['local' => 9000, 'lampung' => 14000, 'java' => 24000], '2-4 hari'],
                ['YES', 'YES', ['local' => 18000, 'lampung' => 26000, 'java' => 39000], '1-2 hari'],
            ],
            'jnt' => [
                ['EZ', 'EZ', ['local' => 8500, 'lampung' => 13000, 'java' => 23000], '2-4 hari'],
                ['Super', 'SP', ['local' => 17000, 'lampung' => 25000, 'java' => 37000], '1-2 hari'],
            ],
            'sicepat' => [
                ['Regular', 'REG', ['local' => 8500, 'lampung' => 13500, 'java' => 23500], '2-4 hari'],
                ['BEST', 'BEST', ['local' => 16500, 'lampung' => 24500, 'java' => 36000], '1-2 hari'],
            ],
            'pos' => [
                ['Pos Reguler', 'REG', ['local' => 8000, 'lampung' => 12500, 'java' => 22000], '3-5 hari'],
            ],
            'tiki' => [
                ['Regular Service', 'REG', ['local' => 9000, 'lampung' => 14500, 'java' => 24500], '2-4 hari'],
            ],
        ];

        $activeExpeditions = [];
        foreach (Expedition::query()->whereIn('code', array_keys($services))->get() as $expedition) {
            $activeExpeditions[$expedition->code] = $expedition;
        }

        foreach ($locations as $location) {
            foreach ($services as $expeditionCode => $serviceRows) {
                $expedition = $activeExpeditions[$expeditionCode] ?? null;
                if (! $expedition) {
                    continue;
                }

                foreach ($serviceRows as [$serviceName, $serviceCode, $prices, $etd]) {
                    ShippingRate::updateOrCreate(
                        [
                            'expedition_id' => $expedition->id,
                            'service_code' => $serviceCode,
                            'district_id' => $location['district_id'],
                        ],
                        [
                            'service_name' => $serviceName,
                            'province' => $location['province'],
                            'province_id' => $location['province_id'],
                            'city' => $location['city'],
                            'city_id' => $location['city_id'],
                            'district' => $location['district'],
                            'postal_code' => $location['postal_code'],
                            'price' => $prices[$location['zone']],
                            'etd' => $etd,
                            'is_active' => true,
                        ],
                    );
                }
            }
        }

        ShippingRate::query()
            ->whereNotIn('district_id', array_column($locations, 'district_id'))
            ->update(['is_active' => false]);
    }
}

