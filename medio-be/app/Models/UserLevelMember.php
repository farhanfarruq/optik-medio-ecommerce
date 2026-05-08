<?php

namespace App\Models;

use App\Enums\MembershipAssignmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLevelMember extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $membership): void {
            $membership->active_membership_user_id = $membership->effective_until === null
                ? $membership->user_id
                : null;
        });
    }

    protected $fillable = [
        'user_id',
        'level_member_id',
        'points_snapshot',
        'assignment_type',
        'assigned_by',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'points_snapshot' => 'integer',
        'assignment_type' => MembershipAssignmentType::class,
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function levelMember(): BelongsTo
    {
        return $this->belongsTo(LevelMember::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
