<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class SyncCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items'                          => 'required|array',
            'items.*.product_id'             => 'required|exists:products,id',
            'items.*.quantity'               => 'required|integer|min:1',
            'items.*.variant'                => 'nullable|array',
            'items.*.prescription'           => 'nullable|array',
            'items.*.lens_option_id'         => 'nullable|exists:lens_options,id',
            'items.*.lens_coating_id'        => 'nullable|exists:lens_coatings,id',
            'items.*.prescription_profile_id' => 'nullable|exists:prescription_profiles,id',
            'items.*.configuration_snapshot' => 'nullable|array',
        ];
    }
}
