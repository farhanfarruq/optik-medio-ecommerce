<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ServiceClaim;
use App\Models\Warranty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    /**
     * GET /api/warranties
     * Daftar garansi milik user.
     */
    public function index(Request $request): JsonResponse
    {
        $this->syncExpiredWarranties($request->user()->id);

        $warranties = Warranty::with('serviceClaims')
            ->where('user_id', $request->user()->id)
            ->latest('purchase_date')
            ->paginate(10);

        return response()->json($warranties);
    }

    /**
     * GET /api/warranties/{id}
     * Detail garansi + riwayat klaim.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $warranty = Warranty::with(['serviceClaims' => fn ($q) => $q->latest()])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $this->syncExpiredWarranty($warranty);

        return response()->json([
            'warranty'       => $warranty,
            'days_remaining' => $warranty->daysRemaining(),
            'is_active'      => $warranty->isActive(),
        ]);
    }

    /**
     * GET /api/service-claims
     * Daftar klaim servis milik user.
     */
    public function claims(Request $request): JsonResponse
    {
        $claims = ServiceClaim::with('warranty:id,product_name,warranty_number')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json($claims);
    }

    /**
     * POST /api/service-claims
     * Ajukan klaim servis baru.
     */
    public function storeClaim(Request $request): JsonResponse
    {
        $request->validate([
            'warranty_id' => 'nullable|exists:warranties,id',
            'claim_type'  => 'required|in:warranty_repair,lens_replacement,frame_adjustment,cleaning,other',
            'description' => 'required|string|max:1000',
            'images'      => 'nullable|array|max:3',
            'images.*'    => 'file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $warranty = null;

        // Ownership and eligibility check untuk warranty
        if ($request->warranty_id) {
            $warranty = Warranty::where('id', $request->warranty_id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $warranty) {
                return response()->json(['message' => 'Garansi tidak ditemukan.'], 404);
            }

            $this->syncExpiredWarranty($warranty);

            if (! $warranty->isActive()) {
                return response()->json(['message' => 'Garansi sudah tidak aktif atau sudah kadaluarsa.'], 422);
            }

            $hasOpenClaim = $warranty->serviceClaims()
                ->whereIn('status', ['submitted', 'reviewing', 'approved', 'in_progress', 'completed'])
                ->exists();

            if ($hasOpenClaim) {
                return response()->json(['message' => 'Garansi ini sudah pernah diklaim.'], 422);
            }
        }

        // Upload foto klaim
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('service-claims', 'public');
            }
        }

        $claim = ServiceClaim::create([
            'claim_number'           => ServiceClaim::generateNumber(),
            'warranty_id'            => $request->warranty_id,
            'user_id'                => $request->user()->id,
            'claim_type'             => $request->claim_type,
            'status'                 => 'submitted',
            'description'            => $request->description,
            'images'                 => $imagePaths ?: null,
            'is_covered_by_warranty' => (bool) $request->warranty_id,
        ]);

        if ($warranty) {
            $warranty->update(['status' => 'claimed']);
        }

        return response()->json([
            'message' => 'Klaim servis berhasil diajukan. Tim kami akan menghubungi Anda segera.',
            'claim'   => $claim->load('warranty:id,product_name,warranty_number'),
        ], 201);
    }

    private function syncExpiredWarranties(int $userId): void
    {
        Warranty::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereDate('warranty_expires_at', '<', now()->toDateString())
            ->update(['status' => 'expired']);
    }

    private function syncExpiredWarranty(Warranty $warranty): void
    {
        if (
            $warranty->status === 'active'
            && $warranty->warranty_expires_at->copy()->endOfDay()->isPast()
        ) {
            $warranty->update(['status' => 'expired']);
            $warranty->refresh();
        }
    }
}
