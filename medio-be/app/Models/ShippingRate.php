<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'expedition_id',
        'service_name',
        'service_code',
        'province',
        'province_id',
        'city',
        'city_id',
        'district',
        'district_id',
        'postal_code',
        'price',
        'etd',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function expedition(): BelongsTo
    {
        return $this->belongsTo(Expedition::class);
    }
}
