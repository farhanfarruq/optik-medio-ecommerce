<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route uses auth:sanctum middleware — sudah authenticated saat sampai sini.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id'              => 'required|exists:products,id',
            'quantity'                => 'required|integer|min:1|max:99',
            'variant'                 => 'nullable|array',
            'prescription'            => 'nullable|array',
            'lens_option_id'          => 'nullable|exists:lens_options,id',
            'lens_coating_id'         => 'nullable|exists:lens_coatings,id',
            'prescription_profile_id' => 'nullable|exists:prescription_profiles,id',
            'configuration_snapshot'  => 'nullable|array',
        ];
    }
}
