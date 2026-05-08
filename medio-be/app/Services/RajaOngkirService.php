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
        $this->baseUrl = config("services.rajaongkir.base_url");
        $this->apiKey = config("services.rajaongkir.api_key");
        $this->originDistrictId = config(
            "services.rajaongkir.origin_district_id",
        );
    }

    public function getProvinces(): array
    {
        $response = Http::withHeaders(["key" => $this->apiKey])->get(
            $this->baseUrl . "/destination/province",
        );

        return $response->json("data", []);
    }

    public function getCities(string $provinceId = ""): array
    {
        if (!$provinceId) {
            return Http::withHeaders(["key" => $this->apiKey])
                ->get($this->baseUrl . "/destination/city")
                ->json("data", []);
        }

        // Strategy 1: Use documented path-based endpoint
        $response = Http::withHeaders(["key" => $this->apiKey])->get(
            $this->baseUrl . "/destination/city/$provinceId",
        );
        $data = $response->json("data", []);
        if (!empty($data)) {
            return $data;
        }

        // Strategy 2: Legacy query parameter fallbacks
        $paramNames = ["province_id", "id_province", "province", "id"];
        foreach ($paramNames as $name) {
            $response = Http::withHeaders(["key" => $this->apiKey])->get(
                $this->baseUrl . "/destination/city",
                [$name => $provinceId],
            );
            $data = $response->json("data", []);
            if (!empty($data)) {
                return $data;
            }
        }

        // Strategy 3: Legacy alternative path fallback
        $response = Http::withHeaders(["key" => $this->apiKey])->get(
            $this->baseUrl . "/destination/city/province/$provinceId",
        );
        $data = $response->json("data", []);
        if (!empty($data)) {
            return $data;
        }

        return [];
    }

    public function getDistricts(string $cityId = ""): array
    {
        if (!$cityId) {
            return Http::withHeaders(["key" => $this->apiKey])
                ->get($this->baseUrl . "/destination/district")
                ->json("data", []);
        }

        // Strategy 1: Use documented path-based endpoint
        $response = Http::withHeaders(["key" => $this->apiKey])->get(
            $this->baseUrl . "/destination/district/$cityId",
        );
        $data = $response->json("data", []);
        if (!empty($data)) {
            return $data;
        }

        // Strategy 2: Legacy query parameter fallbacks
        $paramNames = ["city_id", "id_city", "city", "id"];
        foreach ($paramNames as $name) {
            $response = Http::withHeaders(["key" => $this->apiKey])->get(
                $this->baseUrl . "/destination/district",
                [$name => $cityId],
            );
            $data = $response->json("data", []);
            if (!empty($data)) {
                return $data;
            }
        }

        // Strategy 3: Legacy alternative path fallback
        $response = Http::withHeaders(["key" => $this->apiKey])->get(
            $this->baseUrl . "/destination/district/city/$cityId",
        );
        $data = $response->json("data", []);
        if (!empty($data)) {
            return $data;
        }

        return [];
    }

    public function calculateAllCouriers(
        string $destinationDistrictId,
        int $weight,
    ): array {
        $activeCouriers = \App\Models\Expedition::where('is_active', true)
            ->pluck('code')
            ->map(fn($code) => strtolower($code))
            ->toArray();
        
        $couriers = implode(':', $activeCouriers);
        if (empty($couriers)) {
            $couriers = "jne:tiki:pos"; // fallback
        }

        $response = Http::withHeaders(["key" => $this->apiKey])
            ->asForm()
            ->post($this->baseUrl . "/calculate/district/domestic-cost", [
                "origin" => $this->originDistrictId,
                "destination" => $destinationDistrictId,
                "weight" => $weight,
                "courier" => $couriers,
                "price" => "lowest",
            ]);

        $data = $response->json("data", []);
        $results = [];

        foreach ($data as $item) {
            $courierCode = strtolower($item["code"] ?? ($item["name"] ?? "UNKNOWN"));
            
            // Find the expedition ID for this courier to sync services
            $expedition = \App\Models\Expedition::where('code', $courierCode)->first();

            if (isset($item["costs"]) && is_array($item["costs"])) {
                foreach ($item["costs"] as $cost) {
                    $serviceCode = $cost["service"] ?? "";
                    
                    // Auto-sync service and check if it's active
                    if ($expedition && $serviceCode) {
                        $expService = \App\Models\ExpeditionService::firstOrCreate(
                            ['expedition_id' => $expedition->id, 'service_code' => $serviceCode],
                            ['service_name' => $cost["description"] ?? "", 'is_active' => true]
                        );
                        
                        // Skip if the admin disabled this service
                        if (!$expService->is_active) {
                            continue;
                        }
                    }

                    $results[] = [
                        "courier" => strtoupper($courierCode),
                        "service" => $serviceCode,
                        "description" => $cost["description"] ?? "",
                        "cost" => $cost["cost"][0]["value"] ?? ($cost["cost"] ?? 0),
                        "etd" => $cost["cost"][0]["etd"] ?? ($cost["etd"] ?? ""),
                    ];
                }
            } else {
                // Fallback for flat structure
                $serviceCode = $item["service"] ?? "";
                if ($expedition && $serviceCode) {
                    $expService = \App\Models\ExpeditionService::firstOrCreate(
                        ['expedition_id' => $expedition->id, 'service_code' => $serviceCode],
                        ['service_name' => $item["description"] ?? "", 'is_active' => true]
                    );
                    if (!$expService->is_active) {
                        continue;
                    }
                }

                $results[] = [
                    "courier" => strtoupper($courierCode),
                    "service" => $serviceCode,
                    "description" => $item["description"] ?? "",
                    "cost" => $item["cost"] ?? ($item["price"] ?? ($item["value"] ?? 0)),
                    "etd" => $item["etd"] ?? ($item["estimation"] ?? ""),
                ];
            }
        }

        return $results;
    }
}
