<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Models\ReferralUse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    /**
     * GET /api/referral/my-code
     * Ambil atau buat referral code untuk user yang login.
     */
    public function myCode(Request $request): JsonResponse
    {
        $referral = ReferralCode::getOrCreateForUser($request->user()->id);

        $uses = ReferralUse::where('inviter_id', $request->user()->id)
            ->with('invitee:id,name,created_at')
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'code'           => $referral->code,
            'total_uses'     => $referral->total_uses,
            'reward_inviter' => $referral->reward_inviter,
            'reward_invitee' => $referral->reward_invitee,
            'is_active'      => $referral->is_active,
            'recent_uses'    => $uses->map(fn ($u) => [
                'invitee_name' => $u->invitee?->name,
                'joined_at'    => $u->created_at?->toDateString(),
                'rewarded'     => $u->inviter_rewarded,
            ]),
            'share_url' => config('app.frontend_url', config('app.url')) . '/referral/' . $referral->code,
        ]);
    }

    /**
     * POST /api/referral/use
     * Gunakan referral code saat registrasi atau pertama kali login.
     * Reward diberikan setelah invitee melakukan order pertama.
     */
    public function use(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $userId = $request->user()->id;

        // Cek apakah user sudah pernah pakai referral
        $alreadyUsed = ReferralUse::where('invitee_id', $userId)->exists();
        if ($alreadyUsed) {
            return response()->json([
                'message' => 'Anda sudah pernah menggunakan kode referral.',
            ], 422);
        }

        $referral = ReferralCode::use($request->code, $userId);

        if (! $referral) {
            return response()->json([
                'message' => 'Kode referral tidak valid atau sudah tidak aktif.',
            ], 422);
        }

        // Beri reward invitee langsung (poin)
        $invitee = $request->user();
        if ($referral->reward_invitee > 0) {
            DB::transaction(function () use ($invitee, $referral, $userId) {
                $invitee->addLoyaltyPoints(
                    $referral->reward_invitee,
                    null,
                    "Bonus referral dari kode {$referral->code}"
                );

                ReferralUse::where('referral_code_id', $referral->id)
                    ->where('invitee_id', $userId)
                    ->update([
                        'invitee_rewarded' => true,
                        'rewarded_at'      => now(),
                    ]);
            });
        }

        return response()->json([
            'message'        => "Kode referral berhasil digunakan! Anda mendapat {$referral->reward_invitee} poin.",
            'points_earned'  => $referral->reward_invitee,
        ]);
    }

    /**
     * POST /api/referral/reward-inviter/{referralUseId}
     * Internal: beri reward ke inviter setelah invitee selesai order pertama.
     * Dipanggil dari OrderController saat order pertama invitee delivered.
     */
    public static function rewardInviterIfEligible(int $inviteeId): void
    {
        $use = ReferralUse::where('invitee_id', $inviteeId)
            ->where('inviter_rewarded', false)
            ->with(['referralCode', 'inviter'])
            ->first();

        if (! $use || ! $use->inviter) {
            return;
        }

        $points = $use->referralCode?->reward_inviter ?? 0;
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($use, $points) {
            $use->inviter->addLoyaltyPoints(
                $points,
                null,
                "Bonus referral: {$use->invitee?->name} melakukan pembelian pertama"
            );

            $use->update([
                'inviter_rewarded' => true,
                'rewarded_at'      => $use->rewarded_at ?? now(),
            ]);
        });
    }

    /**
     * GET /api/referral/validate/{code}
     * Public: validasi kode referral (untuk tampil di halaman registrasi).
     */
    public function validate(string $code): JsonResponse
    {
        $referral = ReferralCode::where('code', strtoupper($code))
            ->where('is_active', true)
            ->with('user:id,name')
            ->first();

        if (! $referral) {
            return response()->json(['valid' => false, 'message' => 'Kode tidak ditemukan.'], 404);
        }

        return response()->json([
            'valid'          => true,
            'inviter_name'   => $referral->user?->name,
            'reward_invitee' => $referral->reward_invitee,
            'message'        => "Kode valid! Anda akan mendapat {$referral->reward_invitee} poin setelah mendaftar.",
        ]);
    }
}
