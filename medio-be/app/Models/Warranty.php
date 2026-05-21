<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Warranty extends Model
{
    protected $fillable = [
        'warranty_number', 'user_id', 'order_id', 'order_item_id',
        'product_name', 'product_sku',
        'purchase_date', 'warranty_expires_at', 'warranty_months',
        'status', 'notes',
    ];

    protected $casts = [
        'purchase_date'       => 'date',
        'warranty_expires_at' => 'date',
        'warranty_months'     => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function serviceClaims(): HasMany
    {
        return $this->hasMany(ServiceClaim::class);
    }

    public static function generateNumber(): string
    {
        return 'WRT-' . strtoupper(Str::random(8));
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->warranty_expires_at->copy()->endOfDay()->isFuture();
    }

    public function daysRemaining(): int
    {
        return max(0, now()->diffInDays($this->warranty_expires_at, false));
    }
}
