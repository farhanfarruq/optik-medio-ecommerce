<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSchedule extends Model
{
    protected $fillable = [
        'branch_id', 'date', 'capacity_override', 'is_closed', 'close_reason',
    ];

    protected $casts = [
        'date'              => 'date',
        'capacity_override' => 'integer',
        'is_closed'         => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(StoreBranch::class, 'branch_id');
    }
}
