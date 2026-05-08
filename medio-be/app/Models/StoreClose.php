<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreClose extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_at',
        'end_at',
        'reason',
        'is_active',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->active()
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now());
    }

    public static function currentActive(): ?self
    {
        return static::query()
            ->current()
            ->orderBy('start_at')
            ->first();
    }
}
