<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReferralCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'total_uses',
        'reward_inviter',
        'reward_invitee',
        'is_active',
    ];

    protected $casts = [
        'total_uses'     => 'integer',
        'reward_inviter' => 'integer',
        'reward_invitee' => 'integer',
        'is_active'      => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uses(): HasMany
    {
        return $this->hasMany(ReferralUse::class);
    }

    /**
     * Generate atau ambil referral code untuk user.
     * Default reward: 50 poin untuk inviter, 25 poin untuk invitee.
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'code'           => self::generateUniqueCode(),
                'reward_inviter' => 50,
                'reward_invitee' => 25,
                'is_active'      => true,
            ]
        );
    }

    /**
     * Generate kode unik 8 karakter uppercase.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Gunakan referral code — catat penggunaan dan beri reward.
     * Fraud guard: invitee tidak bisa pakai kode milik sendiri.
     */
    public static function use(string $code, int $inviteeId): ?self
    {
        $referral = self::where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $referral) {
            return null;
        }

        // Fraud guard: tidak bisa pakai kode sendiri
        if ($referral->user_id === $inviteeId) {
            return null;
        }

        // Cek apakah invitee sudah pernah pakai kode ini
        $alreadyUsed = ReferralUse::where('invitee_id', $inviteeId)->exists();
        if ($alreadyUsed) {
            return null; // satu user hanya bisa pakai satu referral seumur hidup
        }

        ReferralUse::create([
            'referral_code_id' => $referral->id,
            'inviter_id'       => $referral->user_id,
            'invitee_id'       => $inviteeId,
        ]);

        $referral->increment('total_uses');

        return $referral;
    }
}
