<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_affiliator_id',
        'request_no',
        'requested_amount',
        'approved_amount',
        'status',
        'requested_at',
        'processed_at',
        'processed_by',
        'admin_notes',
        'payout_method',
        'payout_bank_name',
        'payout_account_number',
        'payout_account_name',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'status' => CommissionStatus::class,
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $commission): void {
            if (blank($commission->request_no)) {
                $commission->request_no = self::generateRequestNo();
            }

            if (blank($commission->requested_at)) {
                $commission->requested_at = now();
            }
        });
    }

    public static function generateRequestNo(): string
    {
        $year = now()->format('Y');
        $prefix = "COM/{$year}/";

        $latestRequestNo = static::query()
            ->where('request_no', 'like', $prefix . '%')
            ->latest('id')
            ->value('request_no');

        $sequence = $latestRequestNo
            ? ((int) substr($latestRequestNo, -6)) + 1
            : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function affiliator(): BelongsTo
    {
        return $this->belongsTo(UserAffiliator::class, 'user_affiliator_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CommissionDetail::class);
    }
}
