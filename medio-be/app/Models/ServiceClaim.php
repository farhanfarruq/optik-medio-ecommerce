<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ServiceClaim extends Model
{
    protected $fillable = [
        'claim_number', 'warranty_id', 'user_id', 'claim_type',
        'status', 'description', 'images', 'admin_notes',
        'service_cost', 'is_covered_by_warranty', 'resolved_at',
    ];

    protected $casts = [
        'images'                  => 'array',
        'service_cost'            => 'decimal:2',
        'is_covered_by_warranty'  => 'boolean',
        'resolved_at'             => 'datetime',
    ];

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateNumber(): string
    {
        return 'SVC-' . strtoupper(Str::random(8));
    }

    public function getClaimTypeLabelAttribute(): string
    {
        return match ($this->claim_type) {
            'warranty_repair'   => 'Perbaikan Garansi',
            'lens_replacement'  => 'Ganti Lensa',
            'frame_adjustment'  => 'Penyesuaian Frame',
            'cleaning'          => 'Pembersihan',
            'other'             => 'Lainnya',
            default             => ucfirst(str_replace('_', ' ', $this->claim_type)),
        };
    }
}
