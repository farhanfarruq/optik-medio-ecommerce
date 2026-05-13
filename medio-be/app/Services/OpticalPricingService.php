<?php

namespace App\Services;

use App\Models\LensCoating;
use App\Models\LensOption;
use App\Models\PrescriptionProfile;
use App\Models\Product;

class OpticalPricingService
{
    /**
     * @return array<string, mixed>
     */
    public function configure(
        Product $frame,
        ?LensOption $lensOption = null,
        ?LensCoating $lensCoating = null,
        ?PrescriptionProfile $prescriptionProfile = null,
        ?array $prescription = null,
    ): array {
        $framePrice = (float) $frame->price;
        $lensPrice = (float) ($lensOption?->base_price ?? 0);
        $coatingPrice = (float) ($lensCoating?->price ?? 0);
        $warnings = [];

        $compatible = $this->isCompatible($frame, $lensOption);

        if (!$compatible) {
            $warnings[] = 'Lensa tidak kompatibel dengan frame ini.';
        }

        $prescriptionCompatibility = $this->validatePrescription($lensOption, $prescriptionProfile, $prescription);
        if (!$prescriptionCompatibility['compatible']) {
            $compatible = false;
            $warnings = [...$warnings, ...$prescriptionCompatibility['warnings']];
        }

        return [
            'compatible' => $compatible,
            'warnings' => $warnings,
            'price_breakdown' => [
                'frame_price' => $framePrice,
                'lens_price' => $lensPrice,
                'coating_price' => $coatingPrice,
                'total' => $framePrice + $lensPrice + $coatingPrice,
            ],
            'configuration_snapshot' => [
                'frame' => [
                    'id' => $frame->id,
                    'name' => $frame->name,
                    'brand' => $frame->brand,
                    'price' => $framePrice,
                ],
                'lens_option' => $lensOption ? [
                    'id' => $lensOption->id,
                    'name' => $lensOption->name,
                    'type' => $lensOption->type,
                    'base_price' => $lensPrice,
                ] : null,
                'lens_coating' => $lensCoating ? [
                    'id' => $lensCoating->id,
                    'name' => $lensCoating->name,
                    'price' => $coatingPrice,
                ] : null,
                'prescription_profile_id' => $prescriptionProfile?->id,
                'prescription' => $prescriptionProfile ? [
                    'right_sphere' => $prescriptionProfile->right_sphere,
                    'right_cylinder' => $prescriptionProfile->right_cylinder,
                    'right_axis' => $prescriptionProfile->right_axis,
                    'right_add' => $prescriptionProfile->right_add,
                    'left_sphere' => $prescriptionProfile->left_sphere,
                    'left_cylinder' => $prescriptionProfile->left_cylinder,
                    'left_axis' => $prescriptionProfile->left_axis,
                    'left_add' => $prescriptionProfile->left_add,
                    'pd_single' => $prescriptionProfile->pd_single,
                    'pd_right' => $prescriptionProfile->pd_right,
                    'pd_left' => $prescriptionProfile->pd_left,
                ] : $prescription,
            ],
        ];
    }

    private function isCompatible(Product $frame, ?LensOption $lensOption): bool
    {
        if (!$lensOption) {
            return true;
        }

        $compatibilityCount = $frame->lensCompatibilities()->count();

        if ($compatibilityCount === 0) {
            return true;
        }

        return $frame->lensCompatibilities()
            ->where('lens_option_id', $lensOption->id)
            ->exists();
    }

    /**
     * @return array{compatible: bool, warnings: array<int, string>}
     */
    private function validatePrescription(
        ?LensOption $lensOption,
        ?PrescriptionProfile $profile,
        ?array $prescription,
    ): array {
        if (!$lensOption) {
            return ['compatible' => true, 'warnings' => []];
        }

        $rules = $lensOption->prescription_rules ?? [];
        if (!$rules) {
            return ['compatible' => true, 'warnings' => []];
        }

        $values = $profile ? [
            'right_sphere' => $profile->right_sphere,
            'left_sphere' => $profile->left_sphere,
            'right_cylinder' => $profile->right_cylinder,
            'left_cylinder' => $profile->left_cylinder,
        ] : [
            'right_sphere' => data_get($prescription, 'od.sph'),
            'left_sphere' => data_get($prescription, 'os.sph'),
            'right_cylinder' => data_get($prescription, 'od.cyl'),
            'left_cylinder' => data_get($prescription, 'os.cyl'),
        ];

        $warnings = [];

        foreach (['right_sphere', 'left_sphere'] as $key) {
            $value = $values[$key] === null ? null : (float) $values[$key];
            if ($value === null) {
                continue;
            }
            if (isset($rules['min_sphere']) && $value < (float) $rules['min_sphere']) {
                $warnings[] = 'Sphere di luar batas minimum lensa.';
            }
            if (isset($rules['max_sphere']) && $value > (float) $rules['max_sphere']) {
                $warnings[] = 'Sphere di luar batas maksimum lensa.';
            }
        }

        foreach (['right_cylinder', 'left_cylinder'] as $key) {
            $value = $values[$key] === null ? null : (float) $values[$key];
            if ($value === null) {
                continue;
            }
            if (isset($rules['min_cylinder']) && $value < (float) $rules['min_cylinder']) {
                $warnings[] = 'Cylinder di luar batas minimum lensa.';
            }
            if (isset($rules['max_cylinder']) && $value > (float) $rules['max_cylinder']) {
                $warnings[] = 'Cylinder di luar batas maksimum lensa.';
            }
        }

        return [
            'compatible' => count($warnings) === 0,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
