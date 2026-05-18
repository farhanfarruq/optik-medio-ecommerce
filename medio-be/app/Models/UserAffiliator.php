<?php

namespace App\Models;

use App\Enums\UserAffiliatorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserAffiliator extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'affiliate_code',
        'status',
        'commission_rate_percentage',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'notes',
        'payout_method',
        'payout_bank_name',
        'payout_account_number',
        'payout_account_name',
        'payout_notes',
    ];

    protected $casts = [
        'status' => UserAffiliatorStatus::class,
        'commission_rate_percentage' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_affiliator_id');
    }

    public function commissionRequests(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
