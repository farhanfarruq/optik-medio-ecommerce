<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCompatibility extends Model
{
    protected $fillable = [
        'frame_product_id',
        'lens_option_id',
        'compatibility_rule',
    ];

    protected $casts = [
        'compatibility_rule' => 'array',
    ];

    public function frameProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'frame_product_id');
    }

    public function lensOption(): BelongsTo
    {
        return $this->belongsTo(LensOption::class);
    }
}
