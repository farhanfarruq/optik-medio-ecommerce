<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'commission_id',
        'order_id',
        'source_user_id',
        'base_amount',
        'commission_rate_percentage',
        'commission_amount',
        'notes',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'commission_rate_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }
}
