<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LensCoating;
use App\Models\LensOption;
use App\Models\PrescriptionProfile;
use App\Models\Product;
use App\Services\OpticalPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpticalController extends Controller
{
    public function __construct(private OpticalPricingService $pricingService) {}

    public function coatings(): JsonResponse
    {
        return response()->json(
            LensCoating::where('is_active', true)
                ->orderBy('price')
                ->orderBy('name')
                ->get()
        );
    }

    public function configure(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'frame_product_id' => ['required', 'exists:products,id'],
            'lens_option_id' => ['nullable', 'exists:lens_options,id'],
            'lens_coating_id' => ['nullable', 'exists:lens_coatings,id'],
            'prescription_profile_id' => ['nullable', 'exists:prescription_profiles,id'],
            'prescription' => ['nullable', 'array'],
        ]);

        $frame = Product::where('is_active', true)->findOrFail($validated['frame_product_id']);
        $lensOption = !empty($validated['lens_option_id'])
            ? LensOption::where('is_active', true)->findOrFail($validated['lens_option_id'])
            : null;
        $lensCoating = !empty($validated['lens_coating_id'])
            ? LensCoating::where('is_active', true)->findOrFail($validated['lens_coating_id'])
            : null;
        $profile = null;

        if (!empty($validated['prescription_profile_id'])) {
            $profile = PrescriptionProfile::where('user_id', $request->user()->id)
                ->whereKey($validated['prescription_profile_id'])
                ->firstOrFail();
        }

        return response()->json($this->pricingService->configure(
            frame: $frame,
            lensOption: $lensOption,
            lensCoating: $lensCoating,
            prescriptionProfile: $profile,
            prescription: $validated['prescription'] ?? null,
        ));
    }
}
