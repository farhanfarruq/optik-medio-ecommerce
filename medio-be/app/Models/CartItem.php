<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'variant',
        'prescription',
        'lens_option_id',
        'lens_coating_id',
        'prescription_profile_id',
        'configuration_snapshot',
    ];

    protected $casts = [
        'variant'                => 'array',
        'prescription'           => 'array',
        'configuration_snapshot' => 'array',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lensOption(): BelongsTo
    {
        return $this->belongsTo(LensOption::class);
    }

    public function lensCoating(): BelongsTo
    {
        return $this->belongsTo(LensCoating::class);
    }

    public function prescriptionProfile(): BelongsTo
    {
        return $this->belongsTo(PrescriptionProfile::class);
    }
}
