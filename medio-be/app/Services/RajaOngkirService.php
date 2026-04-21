<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RajaOngkirService
{
    private string $baseUrl;
    private string $apiKey;
    private string $originDistrictId;

    public function __construct()
    {
        $this->baseUrl          = config('services.rajaongkir.base_url');
        $this->apiKey           = config('services.rajaongkir.api_key');
        $this->originDistrictId = config('services.rajaongkir.origin_district_id');
    }

    public function getProvinces(): array
    {
        $response = Http::withHeaders(['key' => $this->apiKey])
            ->get($this->baseUrl . '/destination/province');

        return $response->json('data', []);
    }

    public function getCities(string $provinceId = ''): array
    {
        $params = $provinceId ? ['province' => $provinceId] : [];

        $response = Http::withHeaders(['key' => $this->apiKey])
            ->get($this->baseUrl . '/destination/city', $params);

        return $response->json('data', []);
    }

    public function getDistricts(string $cityId = ''): array
    {
        $params = $cityId ? ['city' => $cityId] : [];

        $response = Http::withHeaders(['key' => $this->apiKey])
            ->get($this->baseUrl . '/destination/district', $params);

        return $response->json('data', []);
    }

    public function calculateAllCouriers(string $destinationDistrictId, int $weight): array
    {
        $couriers = 'jne:tiki:pos'; // Can be modified to include more couriers

        $response = Http::withHeaders(['key' => $this->apiKey])
            ->asForm()
            ->post($this->baseUrl . '/calculate/district/domestic-cost', [
                'origin'      => $this->originDistrictId,
                'destination' => $destinationDistrictId,
                'weight'      => $weight,
                'courier'     => $couriers,
                'price'       => 'lowest'
            ]);

        $data = $response->json('data', []);
        $results = [];

        foreach ($data as $item) {
            if (isset($item['costs']) && is_array($item['costs'])) {
                foreach ($item['costs'] as $cost) {
                    $results[] = [
                        'courier'     => strtoupper($item['code'] ?? $item['name'] ?? 'UNKNOWN'),
                        'service'     => $cost['service'] ?? '',
                        'description' => $cost['description'] ?? '',
                        'cost'        => $cost['cost'][0]['value'] ?? $cost['cost'] ?? 0,
                        'etd'         => $cost['cost'][0]['etd'] ?? $cost['etd'] ?? '',
                    ];
                }
            } else {
                $results[] = [
                    'courier'     => strtoupper($item['code'] ?? $item['courier'] ?? $item['name'] ?? 'UNKNOWN'),
                    'service'     => $item['service'] ?? '',
                    'description' => $item['description'] ?? '',
                    'cost'        => $item['cost'] ?? $item['price'] ?? $item['value'] ?? 0,
                    'etd'         => $item['etd'] ?? $item['estimation'] ?? '',
                ];
            }
        }

        return $results;
    }
}
