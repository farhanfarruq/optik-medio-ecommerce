<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PrescriptionProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profiles = PrescriptionProfile::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json($profiles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        $profile = DB::transaction(function () use ($request, $validated): PrescriptionProfile {
            if (!empty($validated['is_default'])) {
                PrescriptionProfile::where('user_id', $request->user()->id)->update(['is_default' => false]);
            }

            return PrescriptionProfile::create([
                ...$validated,
                'user_id' => $request->user()->id,
                'attachment_path' => $this->storeAttachment($request),
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ]);
        });

        return response()->json($profile, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->profileForUser($request, $id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $profile = $this->profileForUser($request, $id);
        $validated = $this->validatedPayload($request, partial: true);

        DB::transaction(function () use ($request, $profile, $validated): void {
            if (!empty($validated['is_default'])) {
                PrescriptionProfile::where('user_id', $request->user()->id)
                    ->whereKeyNot($profile->id)
                    ->update(['is_default' => false]);
            }

            $attachmentPath = $this->storeAttachment($request);

            $profile->update([
                ...$validated,
                ...($attachmentPath ? ['attachment_path' => $attachmentPath] : []),
            ]);
        });

        return response()->json($profile->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->profileForUser($request, $id)->delete();

        return response()->json(['message' => 'Resep berhasil dihapus.']);
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $profile = $this->profileForUser($request, $id);

        DB::transaction(function () use ($request, $profile): void {
            PrescriptionProfile::where('user_id', $request->user()->id)->update(['is_default' => false]);
            $profile->update(['is_default' => true]);
        });

        return response()->json($profile->fresh());
    }

    private function profileForUser(Request $request, int $id): PrescriptionProfile
    {
        return PrescriptionProfile::where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $validated = $request->validate([
            'label' => [$required, 'string', 'max:255'],
            'lens_type' => ['nullable', 'string', 'in:single_vision,progressive,reading,blue_light,photochromic,high_index,anti_radiation'],
            'right_sphere' => ['nullable', 'numeric', 'between:-30,30'],
            'right_cylinder' => ['nullable', 'numeric', 'between:-10,10'],
            'right_axis' => ['nullable', 'integer', 'between:0,180'],
            'right_add' => ['nullable', 'numeric', 'between:0,5'],
            'left_sphere' => ['nullable', 'numeric', 'between:-30,30'],
            'left_cylinder' => ['nullable', 'numeric', 'between:-10,10'],
            'left_axis' => ['nullable', 'integer', 'between:0,180'],
            'left_add' => ['nullable', 'numeric', 'between:0,5'],
            'pd_single' => ['nullable', 'numeric', 'between:20,80'],
            'pd_right' => ['nullable', 'numeric', 'between:10,45'],
            'pd_left' => ['nullable', 'numeric', 'between:10,45'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'is_default' => ['boolean'],
        ]);

        $this->validateOpticalRules($validated);
        unset($validated['attachment']);

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function validateOpticalRules(array $validated): void
    {
        foreach (['right', 'left'] as $side) {
            $cylinder = (float) ($validated["{$side}_cylinder"] ?? 0);

            if ($cylinder !== 0.0 && !isset($validated["{$side}_axis"])) {
                throw ValidationException::withMessages([
                    "{$side}_axis" => 'Axis wajib diisi jika cylinder diisi.',
                ]);
            }
        }

        $hasAdd = isset($validated['right_add']) || isset($validated['left_add']);
        $allowsAdd = in_array($validated['lens_type'] ?? null, ['progressive', 'reading'], true);

        if ($hasAdd && !$allowsAdd) {
            throw ValidationException::withMessages([
                'lens_type' => 'ADD hanya boleh digunakan untuk lensa progressive atau reading.',
            ]);
        }

        $hasSinglePd = isset($validated['pd_single']);
        $hasDualPd = isset($validated['pd_right'], $validated['pd_left']);

        if (!$hasSinglePd && !$hasDualPd) {
            throw ValidationException::withMessages([
                'pd_single' => 'Isi PD tunggal atau PD kanan dan kiri.',
            ]);
        }
    }

    private function storeAttachment(Request $request): ?string
    {
        if (!$request->hasFile('attachment')) {
            return null;
        }

        return $request->file('attachment')->store('prescriptions', 'public');
    }
}
