<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralUse extends Model
{
    protected $fillable = [
        'referral_code_id',
        'inviter_id',
        'invitee_id',
        'inviter_rewarded',
        'invitee_rewarded',
        'rewarded_at',
    ];

    protected $casts = [
        'inviter_rewarded' => 'boolean',
        'invitee_rewarded' => 'boolean',
        'rewarded_at'      => 'datetime',
    ];

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}
