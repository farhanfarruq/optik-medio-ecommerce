<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LevelMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'min_points',
        'discount_percentage',
        'sort_order',
        'is_active',
        'description',
    ];

    protected $casts = [
        'min_points' => 'integer',
        'discount_percentage' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function userMemberships(): HasMany
    {
        return $this->hasMany(UserLevelMember::class);
    }
}
