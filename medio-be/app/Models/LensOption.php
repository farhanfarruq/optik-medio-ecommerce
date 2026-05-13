<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LensOption extends Model
{
    protected $fillable = [
        'name',
        'type',
        'base_price',
        'prescription_rules',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'float',
        'prescription_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function productCompatibilities(): HasMany
    {
        return $this->hasMany(ProductCompatibility::class);
    }

    public function compatibleFrames(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_compatibilities', 'lens_option_id', 'frame_product_id')
            ->withPivot('compatibility_rule')
            ->withTimestamps();
    }
}
