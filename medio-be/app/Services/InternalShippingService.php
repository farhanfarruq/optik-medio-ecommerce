<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\ShippingAddress;
use App\Models\ShippingRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class InternalShippingService
{
    public function getExpeditions(): Collection
    {
        return Expedition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getProvinces(): Collection
    {
        return ShippingRate::query()
            ->where('is_active', true)
            ->select('province', 'province_id')
            ->distinct()
            ->orderBy('province')
            ->get();
    }

    public function getCities(?string $provinceId = null, ?string $province = null): Collection
    {
        return $this->baseRateQuery($provinceId, $province)
            ->select('city', 'city_id', 'province', 'province_id')
            ->distinct()
            ->orderBy('city')
            ->get();
    }

    public function getDistricts(
        ?string $cityId = null,
        ?string $city = null,
        ?string $provinceId = null,
        ?string $province = null,
    ): Collection {
        $query = $this->baseRateQuery($provinceId, $province);

        if ($cityId) {
            $query->where('city_id', $cityId);
        } elseif ($city) {
            $query->where('city', $city);
        }

        return $query
            ->select('district', 'district_id', 'city', 'city_id')
            ->distinct()
            ->orderBy('district')
            ->get();
    }

    public function calculateCosts(
        ?string $districtId,
        ?string $district,
        ?string $cityId,
        ?string $city,
        ?string $provinceId,
        ?string $province,
        int $weight,
    ): array {
        $rates = $this->matchRates(
            $districtId,
            $district,
            $cityId,
            $city,
            $provinceId,
            $province,
        );

        return $rates->map(function (ShippingRate $rate) use ($weight) {
            return [
                'shipping_rate_id' => $rate->id,
                'courier' => $rate->expedition->code,
                'courier_name' => $rate->expedition->name,
                'service' => $rate->service_code,
                'service_name' => $rate->service_name,
                'description' => $rate->service_name,
                'cost' => (float) $rate->price,
                'etd' => $rate->etd,
                'weight' => $weight,
            ];
        })->values()->all();
    }

    public function resolveRateForAddress(
        ShippingAddress $address,
        ?int $shippingRateId = null,
        ?string $courier = null,
        ?string $serviceCode = null,
    ): ShippingRate {
        $query = ShippingRate::query()
            ->with('expedition')
            ->where('is_active', true);

        if ($shippingRateId) {
            $query->whereKey($shippingRateId);
        } else {
            if ($courier) {
                $query->whereHas('expedition', fn ($expeditionQuery) => $expeditionQuery->where('code', $courier));
            }

            if ($serviceCode) {
                $query->where('service_code', $serviceCode);
            }
        }

        $query->where(function ($locationQuery) use ($address) {
            if ($address->district_id) {
                $locationQuery->orWhere('district_id', $address->district_id);
            }

            $locationQuery->orWhere(function ($fallbackQuery) use ($address) {
                $fallbackQuery
                    ->where('district', $address->district)
                    ->where('city', $address->city)
                    ->where('province', $address->province);
            });
        });

        $rate = $query
            ->orderBy('price')
            ->first();

        if (!$rate) {
            throw ValidationException::withMessages([
                'shipping_rate' => ['Tarif ongkir internal untuk alamat dan kurir yang dipilih tidak ditemukan.'],
            ]);
        }

        return $rate;
    }

    private function baseRateQuery(?string $provinceId = null, ?string $province = null)
    {
        $query = ShippingRate::query()->where('is_active', true);

        if ($provinceId) {
            $query->where('province_id', $provinceId);
        } elseif ($province) {
            $query->where('province', $province);
        }

        return $query;
    }

    private function matchRates(
        ?string $districtId,
        ?string $district,
        ?string $cityId,
        ?string $city,
        ?string $provinceId,
        ?string $province,
    ): Collection {
        $query = ShippingRate::query()
            ->with('expedition')
            ->where('is_active', true)
            ->whereHas('expedition', fn ($expeditionQuery) => $expeditionQuery->where('is_active', true));

        $query->where(function ($locationQuery) use ($districtId, $district, $cityId, $city, $provinceId, $province) {
            if ($districtId) {
                $locationQuery->orWhere('district_id', $districtId);
            }

            if ($district && $city && $province) {
                $locationQuery->orWhere(function ($fallbackQuery) use ($district, $city, $province) {
                    $fallbackQuery
                        ->where('district', $district)
                        ->where('city', $city)
                        ->where('province', $province);
                });
            }

            if ($cityId) {
                $locationQuery->orWhere('city_id', $cityId);
            }

            if ($provinceId) {
                $locationQuery->orWhere('province_id', $provinceId);
            }
        });

        return $query
            ->orderBy('price')
            ->get();
    }
}
