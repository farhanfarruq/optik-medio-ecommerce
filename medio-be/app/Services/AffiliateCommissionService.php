<?php

namespace App\Services;

use App\Enums\CommissionStatus;
use App\Enums\UserAffiliatorStatus;
use App\Models\Commission;
use App\Models\CommissionDetail;
use App\Models\Order;
use App\Models\ReferralUse;
use App\Models\UserAffiliator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AffiliateCommissionService
{
    public const MIN_PAYOUT_AMOUNT = 10000;

    private const ELIGIBLE_ORDER_STATUSES = ['delivered', 'completed'];

    private const LOCKING_COMMISSION_STATUSES = [
        CommissionStatus::Pending->value,
        CommissionStatus::Processing->value,
        CommissionStatus::Success->value,
    ];

    public function summary(UserAffiliator $affiliator): array
    {
        $earnings = $this->availableEarnings($affiliator);
        $referralCustomerIds = $this->referralCustomerIds($affiliator);
        $commissions = Commission::query()
            ->where('user_affiliator_id', $affiliator->id)
            ->get();

        $totalPending = (float) $commissions
            ->where('status', CommissionStatus::Pending)
            ->sum('requested_amount');
        $totalProcessing = (float) $commissions
            ->where('status', CommissionStatus::Processing)
            ->sum('requested_amount');
        $paidOut = (float) $commissions
            ->where('status', CommissionStatus::Success)
            ->sum(fn (Commission $commission) => (float) ($commission->approved_amount ?: $commission->requested_amount));

        return [
            'referrals_count' => $affiliator->referrals_count ?? $referralCustomerIds->count(),
            'total_requests' => (float) $commissions->sum('requested_amount'),
            'total_earned' => (float) $earnings->sum('total_commission'),
            'available_balance' => (float) $earnings->sum('remaining_commission'),
            'locked_balance' => $totalPending + $totalProcessing,
            'paid_out' => $paidOut,
            'total_success' => $paidOut,
            'total_pending' => $totalPending,
            'total_processing' => $totalProcessing,
            'total_cancelled' => (float) $commissions
                ->where('status', CommissionStatus::Cancelled)
                ->sum('requested_amount'),
            'eligible_orders_count' => $earnings->count(),
            'available_orders_count' => $earnings
                ->filter(fn (array $earning): bool => $earning['remaining_commission'] > 0)
                ->count(),
            'minimum_payout_amount' => self::MIN_PAYOUT_AMOUNT,
        ];
    }

    public function requestPayout(UserAffiliator $affiliator, float $requestedAmount, ?string $adminNotes = null): Commission
    {
        if ($requestedAmount < self::MIN_PAYOUT_AMOUNT) {
            throw ValidationException::withMessages([
                'requested_amount' => 'Minimal pencairan komisi adalah Rp ' . number_format(self::MIN_PAYOUT_AMOUNT, 0, ',', '.'),
            ]);
        }

        return DB::transaction(function () use ($affiliator, $requestedAmount, $adminNotes): Commission {
            $earnings = $this->availableEarnings($affiliator)
                ->filter(fn (array $earning): bool => $earning['remaining_commission'] > 0)
                ->values();

            $availableBalance = round((float) $earnings->sum('remaining_commission'), 2);
            $requestedAmount = round($requestedAmount, 2);

            if ($requestedAmount > $availableBalance) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Saldo komisi tersedia tidak mencukupi. Saldo tersedia: Rp ' . number_format($availableBalance, 0, ',', '.'),
                ]);
            }

            $commission = Commission::create([
                'user_affiliator_id' => $affiliator->id,
                'requested_amount' => $requestedAmount,
                'approved_amount' => 0,
                'status' => CommissionStatus::Pending,
                'requested_at' => now(),
                'admin_notes' => $adminNotes,
                'payout_method' => $affiliator->payout_method,
                'payout_bank_name' => $affiliator->payout_bank_name,
                'payout_account_number' => $affiliator->payout_account_number,
                'payout_account_name' => $affiliator->payout_account_name,
            ]);

            $remainingRequest = $requestedAmount;

            foreach ($earnings as $earning) {
                if ($remainingRequest <= 0) {
                    break;
                }

                $allocatedCommission = min($earning['remaining_commission'], $remainingRequest);
                $allocatedBase = $earning['total_commission'] > 0
                    ? round($earning['base_amount'] * ($allocatedCommission / $earning['total_commission']), 2)
                    : 0;

                CommissionDetail::create([
                    'commission_id' => $commission->id,
                    'order_id' => $earning['order']->id,
                    'source_user_id' => $earning['order']->user_id,
                    'base_amount' => $allocatedBase,
                    'commission_rate_percentage' => $earning['commission_rate_percentage'],
                    'commission_amount' => round($allocatedCommission, 2),
                    'notes' => 'Komisi dari order #' . $earning['order']->order_number,
                ]);

                $remainingRequest = round($remainingRequest - $allocatedCommission, 2);
            }

            if ($remainingRequest > 0.01) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Saldo komisi tidak cukup untuk membuat rincian pencairan.',
                ]);
            }

            return $commission->fresh(['details.order', 'details.sourceUser']);
        });
    }

    public function availableEarnings(UserAffiliator $affiliator): Collection
    {
        if ($affiliator->status !== UserAffiliatorStatus::Approved) {
            return collect();
        }

        $rate = (float) $affiliator->commission_rate_percentage;
        if ($rate <= 0) {
            return collect();
        }

        $claimedByOrder = CommissionDetail::query()
            ->selectRaw('order_id, COALESCE(SUM(commission_amount), 0) as claimed_amount')
            ->whereHas('commission', fn ($query) => $query
                ->where('user_affiliator_id', $affiliator->id)
                ->whereIn('status', self::LOCKING_COMMISSION_STATUSES))
            ->whereNotNull('order_id')
            ->groupBy('order_id')
            ->pluck('claimed_amount', 'order_id');

        $referralCustomerIds = $this->referralCustomerIds($affiliator);

        if ($referralCustomerIds->isEmpty()) {
            return collect();
        }

        return Order::query()
            ->with('user')
            ->whereIn('status', self::ELIGIBLE_ORDER_STATUSES)
            ->whereIn('user_id', $referralCustomerIds)
            ->orderByRaw('COALESCE(delivered_at, created_at) asc')
            ->get()
            ->map(function (Order $order) use ($rate, $claimedByOrder): array {
                $baseAmount = $this->commissionBaseAmount($order);
                $totalCommission = round($baseAmount * ($rate / 100), 2);
                $claimedAmount = round((float) ($claimedByOrder[$order->id] ?? 0), 2);

                return [
                    'order' => $order,
                    'base_amount' => $baseAmount,
                    'commission_rate_percentage' => $rate,
                    'total_commission' => $totalCommission,
                    'claimed_commission' => $claimedAmount,
                    'remaining_commission' => max(0, round($totalCommission - $claimedAmount, 2)),
                ];
            })
            ->filter(fn (array $earning): bool => $earning['total_commission'] > 0)
            ->values();
    }

    public function earningsForResponse(UserAffiliator $affiliator): Collection
    {
        return $this->availableEarnings($affiliator)
            ->map(fn (array $earning): array => [
                'order_id' => $earning['order']->id,
                'order_number' => $earning['order']->order_number,
                'customer_name' => $earning['order']->user?->name ?? 'Customer',
                'status' => $earning['order']->status,
                'created_at' => $earning['order']->created_at?->toISOString(),
                'delivered_at' => $earning['order']->delivered_at?->toISOString(),
                'base_amount' => $earning['base_amount'],
                'commission_rate_percentage' => $earning['commission_rate_percentage'],
                'total_commission' => $earning['total_commission'],
                'claimed_commission' => $earning['claimed_commission'],
                'remaining_commission' => $earning['remaining_commission'],
                'is_available_for_payout' => $earning['remaining_commission'] > 0,
            ]);
    }

    private function referralCustomerIds(UserAffiliator $affiliator): Collection
    {
        $directReferralIds = $affiliator->referrals()
            ->pluck('users.id');

        $referralCodeUseIds = ReferralUse::query()
            ->where('inviter_id', $affiliator->user_id)
            ->pluck('invitee_id');

        return $directReferralIds
            ->merge($referralCodeUseIds)
            ->filter()
            ->unique()
            ->values();
    }

    private function commissionBaseAmount(Order $order): float
    {
        $discounts = (float) ($order->discount_amount ?? 0)
            + (float) ($order->promo_discount_amount ?? 0)
            + (float) ($order->level_discount_amount ?? 0)
            + (float) ($order->loyalty_discount_amount ?? 0);

        $baseAmount = (float) ($order->subtotal ?? 0) - $discounts;

        if ($baseAmount <= 0) {
            $baseAmount = (float) ($order->total_price ?? 0)
                - (float) ($order->shipping_cost ?? 0)
                - (float) ($order->shipping_protection_fee ?? 0);
        }

        return round(max(0, $baseAmount), 2);
    }
}
