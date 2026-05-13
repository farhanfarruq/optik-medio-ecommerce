<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'status',
        'last_activity_at',
        'abandoned_reminder_sent_at',
    ];

    protected $casts = [
        'last_activity_at'            => 'datetime',
        'abandoned_reminder_sent_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Ambil atau buat cart aktif untuk user.
     */
    public static function activeForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'status' => 'active'],
            ['last_activity_at' => now()]
        );
    }

    /**
     * Ambil atau buat cart aktif untuk session (guest).
     */
    public static function activeForSession(string $sessionId): self
    {
        return self::firstOrCreate(
            ['session_id' => $sessionId, 'status' => 'active', 'user_id' => null],
            ['last_activity_at' => now()]
        );
    }

    /**
     * Merge guest cart ke user cart setelah login.
     */
    public static function mergeGuestToUser(string $sessionId, int $userId): void
    {
        $guestCart = self::where('session_id', $sessionId)
            ->where('status', 'active')
            ->whereNull('user_id')
            ->with('items')
            ->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        $userCart = self::activeForUser($userId);

        foreach ($guestCart->items as $guestItem) {
            $existing = $userCart->items()
                ->where('product_id', $guestItem->product_id)
                ->first();

            if ($existing) {
                $existing->increment('quantity', $guestItem->quantity);
            } else {
                $userCart->items()->create([
                    'product_id'              => $guestItem->product_id,
                    'quantity'                => $guestItem->quantity,
                    'variant'                 => $guestItem->variant,
                    'prescription'            => $guestItem->prescription,
                    'lens_option_id'          => $guestItem->lens_option_id,
                    'lens_coating_id'         => $guestItem->lens_coating_id,
                    'prescription_profile_id' => $guestItem->prescription_profile_id,
                    'configuration_snapshot'  => $guestItem->configuration_snapshot,
                ]);
            }
        }

        $guestCart->update(['status' => 'merged']);
        $userCart->touch();
    }

    /**
     * Tandai cart sebagai converted (setelah order berhasil dibuat).
     */
    public function markConverted(): void
    {
        $this->update(['status' => 'converted']);
    }
}
