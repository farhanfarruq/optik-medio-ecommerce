<?php

namespace App\Http\Controllers\API;

use App\Enums\CommissionStatus;
use App\Enums\UserAffiliatorStatus;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\UserAffiliator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $affiliator = $request->user()
            ->affiliateProfile()
            ->withCount('referrals')
            ->first();

        if (! $affiliator) {
            return response()->json([
                'success' => true,
                'message' => 'User belum memiliki profil affiliator.',
                'data' => [
                    'profile' => null,
                    'summary' => $this->emptySummary(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard affiliator berhasil diambil.',
            'data' => [
                'profile' => $affiliator,
                'summary' => $this->buildSummary($affiliator),
            ],
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $existingAffiliator = $request->user()->affiliateProfile;

        if ($existingAffiliator) {
            return response()->json([
                'success' => true,
                'message' => 'Profil affiliator sudah tersedia.',
                'data' => $existingAffiliator,
            ]);
        }

        $affiliator = UserAffiliator::create([
            'user_id' => $request->user()->id,
            'affiliate_code' => $this->generateAffiliateCode($request->user()->name),
            'status' => UserAffiliatorStatus::Pending,
            'commission_rate_percentage' => 5,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan affiliator berhasil dibuat dan menunggu persetujuan admin.',
            'data' => $affiliator,
        ], 201);
    }

    public function commissions(Request $request): JsonResponse
    {
        $affiliator = $request->user()->affiliateProfile;

        if (! $affiliator) {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada data komisi karena user belum menjadi affiliator.',
                'data' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                ],
            ]);
        }

        // ── AFIL-005: Hanya afiliator approved yang bisa lihat komisi ─────────
        if ($affiliator->status !== UserAffiliatorStatus::Approved) {
            return response()->json([
                'success' => false,
                'message' => 'Akun affiliator Anda belum disetujui. Komisi hanya tersedia setelah akun diaktifkan oleh admin.',
                'data' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                ],
            ]);
        }

        $commissions = Commission::query()
            ->with('details')
            ->where('user_affiliator_id', $affiliator->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat komisi berhasil diambil.',
            'data' => $commissions,
        ]);
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $request->validate([
            'requested_amount' => 'required|numeric|min:10000',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $affiliator = $request->user()->affiliateProfile;

        if (! $affiliator) {
            return response()->json([
                'message' => 'Anda belum memiliki profil affiliator.',
            ], 422);
        }

        if ($affiliator->status !== UserAffiliatorStatus::Approved) {
            return response()->json([
                'message' => 'Akun affiliator Anda belum aktif. Pencairan baru bisa diajukan setelah disetujui admin.',
            ], 422);
        }

        $hasOpenRequest = Commission::query()
            ->where('user_affiliator_id', $affiliator->id)
            ->whereIn('status', [CommissionStatus::Pending, CommissionStatus::Processing])
            ->exists();

        if ($hasOpenRequest) {
            return response()->json([
                'message' => 'Masih ada pencairan komisi yang sedang diproses.',
            ], 422);
        }

        $summary = $this->buildSummary($affiliator);
        $availableBalance = $summary['total_success'] - ($summary['total_pending'] + $summary['total_processing']);

        if ($request->input('requested_amount') > $availableBalance) {
            return response()->json([
                'message' => 'Saldo komisi Anda tidak mencukupi untuk penarikan sebesar Rp ' . number_format($request->input('requested_amount'), 0, ',', '.'),
            ], 422);
        }

        $commission = Commission::create([
            'user_affiliator_id' => $affiliator->id,
            'requested_amount' => $request->input('requested_amount'),
            'approved_amount' => null,
            'status' => CommissionStatus::Pending,
            'requested_at' => now(),
            'admin_notes' => $request->input('admin_notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan pencairan komisi berhasil diajukan.',
            'data' => $commission->fresh('details'),
        ], 201);
    }

    private function buildSummary(UserAffiliator $affiliator): array
    {
        $commissions = Commission::query()
            ->where('user_affiliator_id', $affiliator->id)
            ->get();

        // ── AFIL-004: Hitung komisi hanya dari order yang sudah selesai ───────
        $eligibleCommissionIds = \App\Models\CommissionDetail::query()
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))
            ->pluck('commission_id')
            ->unique();

        return [
            'referrals_count' => $affiliator->referrals_count ?? $affiliator->referrals()->count(),
            'total_requests' => (float) $commissions->sum('requested_amount'),
            'total_success' => (float) $commissions
                ->where('status', CommissionStatus::Success)
                ->sum(fn (Commission $commission) => $commission->approved_amount ?? $commission->requested_amount),
            'total_pending' => (float) $commissions
                ->where('status', CommissionStatus::Pending)
                ->sum('requested_amount'),
            'total_processing' => (float) $commissions
                ->where('status', CommissionStatus::Processing)
                ->sum('requested_amount'),
            'total_cancelled' => (float) $commissions
                ->where('status', CommissionStatus::Cancelled)
                ->sum('requested_amount'),
            'eligible_commission_ids' => $eligibleCommissionIds->values(),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'referrals_count' => 0,
            'total_requests' => 0,
            'total_success' => 0,
            'total_pending' => 0,
            'total_processing' => 0,
            'total_cancelled' => 0,
        ];
    }

    private function generateAffiliateCode(string $name): string
    {
        $base = Str::of($name)
            ->upper()
            ->ascii()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->substr(0, 6)
            ->value();

        if ($base === '') {
            $base = 'MEDIO';
        }

        do {
            $code = $base . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (UserAffiliator::query()->where('affiliate_code', $code)->exists());

        return $code;
    }
}
