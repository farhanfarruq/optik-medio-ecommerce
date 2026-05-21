<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    // Role constants
    const ROLE_OWNER           = 'owner';
    const ROLE_ADMIN           = 'admin';
    const ROLE_FINANCE         = 'finance';
    const ROLE_WAREHOUSE       = 'warehouse';
    const ROLE_CUSTOMER_SERVICE = 'customer_service';
    const ROLE_CONTENT_MANAGER = 'content_manager';
    const ROLE_USER            = 'user';

    /** Semua role yang dianggap staff (bisa login ke admin panel). */
    const STAFF_ROLES = [
        self::ROLE_OWNER,
        self::ROLE_ADMIN,
        self::ROLE_FINANCE,
        self::ROLE_WAREHOUSE,
        self::ROLE_CUSTOMER_SERVICE,
        self::ROLE_CONTENT_MANAGER,
    ];

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'loyalty_points', 'referred_by_affiliator_id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'loyalty_points'    => 'integer',
    ];

    /** Cek apakah user adalah staff (bisa akses admin panel). */
    public function isStaff(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }

    /** Cek apakah user punya role tertentu. */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Cek apakah user adalah owner atau admin penuh. */
    public function isOwnerOrAdmin(): bool
    {
        return $this->hasRole(self::ROLE_OWNER, self::ROLE_ADMIN);
    }

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

    public function prescriptionProfiles(): HasMany
    {
        return $this->hasMany(PrescriptionProfile::class);
    }

    /**
     * Tambah poin loyalty dan catat log-nya (atomic).
     */
    public function addLoyaltyPoints(int $points, ?int $orderId = null, string $description = ''): void
    {
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($points, $orderId, $description) {
            $this->increment('loyalty_points', $points);

            LoyaltyPointLog::create([
                'user_id'     => $this->id,
                'order_id'    => $orderId,
                'points'      => $points,
                'type'        => 'earned',
                'description' => $description ?: "Poin dari pesanan",
            ]);
        });

        $this->updateMembershipLevel();
    }

    /**
     * Kurangi poin loyalty (redeem) dan catat log-nya (atomic).
     */
    public function redeemLoyaltyPoints(int $points, ?int $orderId = null, string $description = ''): bool
    {
        if ($points <= 0) {
            return false;
        }

        $redeemed = DB::transaction(function () use ($points, $orderId, $description): bool {
            $user = self::query()
                ->whereKey($this->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($user->loyalty_points < $points) {
                return false;
            }

            $user->decrement('loyalty_points', $points);

            LoyaltyPointLog::create([
                'user_id'     => $user->id,
                'order_id'    => $orderId,
                'points'      => -$points,
                'type'        => 'redeemed',
                'description' => $description ?: "Poin digunakan untuk diskon",
            ]);

            $this->setAttribute('loyalty_points', $user->fresh()->loyalty_points);

            return true;
        });

        if ($redeemed) {
            $this->updateMembershipLevel();
        }

        return $redeemed;
    }

    /**
     * Perbarui level member berdasarkan jumlah poin saat ini.
     */
    public function updateMembershipLevel(): void
    {
        $newLevel = LevelMember::where('is_active', true)
            ->where('min_points', '<=', $this->loyalty_points)
            ->orderBy('min_points', 'desc')
            ->first();

        if (!$newLevel) {
            return;
        }

        $currentMembership = $this->currentLevelMembership;

        if (!$currentMembership || $currentMembership->level_member_id !== $newLevel->id) {
            // Nonaktifkan membership saat ini jika ada
            if ($currentMembership) {
                $currentMembership->update([
                    'effective_until'           => now(),
                    'active_membership_user_id' => null,
                ]);
            }

            // Buat membership baru
            UserLevelMember::create([
                'user_id'                   => $this->id,
                'level_member_id'           => $newLevel->id,
                'points_snapshot'           => $this->loyalty_points,
                'assignment_type'           => \App\Enums\MembershipAssignmentType::Auto,
                'effective_from'            => now(),
            ]);
        }
    }

    /**
     * Filament: izinkan akses admin panel hanya untuk staff roles.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff();
    }
}
