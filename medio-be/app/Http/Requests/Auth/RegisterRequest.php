<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * P1-7 (Phase 3): extracted dari AuthController::register inline validate().
 *
 * Pertahankan rules persis sama untuk avoid behavior change. Custom messages
 * Indonesian dipertahankan di language file (resources/lang/id/validation.php)
 * jika ada — kalau tidak ada, fallback ke default Laravel.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint /auth/register is public; auth handled by route middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'                   => 'required|string|max:255',
            'email'                  => 'required|string|email|max:255|unique:users',
            'phone'                  => 'nullable|string|max:20',
            'password'               => 'required|string|min:8|confirmed',
            'register_as_affiliator' => 'nullable|boolean',
            'referral_code'          => 'nullable|string|max:50',
        ];
    }
}
