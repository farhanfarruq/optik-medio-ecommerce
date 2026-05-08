<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'loyalty_points', 'referred_by_affiliator_id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'loyalty_points'    => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(ShippingAddress::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ShippingAddress::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function levelMemberships(): HasMany
    {
        return $this->hasMany(UserLevelMember::class);
    }

    public function currentLevelMembership(): HasOne
    {
        return $this->hasOne(UserLevelMember::class)->whereNull('effective_until');
    }

    public function affiliateProfile(): HasOne
    {
        return $this->hasOne(UserAffiliator::class);
    }

    public function referredByAffiliator(): BelongsTo
    {
        return $this->belongsTo(UserAffiliator::class, 'referred_by_affiliator_id');
    }

    public function loyaltyLogs(): HasMany
    {
        return $this->hasMany(LoyaltyPointLog::class);
    }

    public function complains(): HasMany
    {
        return $this->hasMany(Complain::class);
    }

    /**
     * Tambah poin loyalty dan catat log-nya.
     */
    public function addLoyaltyPoints(int $points, ?int $orderId = null, string $description = ''): void
    {
        $this->increment('loyalty_points', $points);

        LoyaltyPointLog::create([
            'user_id'     => $this->id,
            'order_id'    => $orderId,
            'points'      => $points,
            'type'        => 'earned',
            'description' => $description ?: "Poin dari pesanan",
        ]);
    }

    /**
     * Kurangi poin loyalty (redeem) dan catat log-nya.
     */
    public function redeemLoyaltyPoints(int $points, ?int $orderId = null, string $description = ''): bool
    {
        if ($this->loyalty_points < $points) {
            return false;
        }

        $this->decrement('loyalty_points', $points);

        LoyaltyPointLog::create([
            'user_id'     => $this->id,
            'order_id'    => $orderId,
            'points'      => -$points,
            'type'        => 'redeemed',
            'description' => $description ?: "Poin digunakan untuk diskon",
        ]);

        return true;
    }
}
