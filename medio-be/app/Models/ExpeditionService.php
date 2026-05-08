<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpeditionService extends Model
{
    use HasFactory;

    protected $fillable = [
        'expedition_id',
        'service_code',
        'service_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function expedition(): BelongsTo
    {
        return $this->belongsTo(Expedition::class);
    }
}
