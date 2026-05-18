<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    use SoftDeletes;

    public const STATUS_OPTIONS = [
        'unpaid'                      => 'Belum Bayar',
        'paid'                        => 'Sudah Bayar',
        'waiting_prescription_review' => 'Menunggu Review Resep',
        'prescription_verified'       => 'Resep Diverifikasi',
        'lens_processing'             => 'Proses Lensa',
        'processing'                  => 'Diproses',
        'shipped'                     => 'Dikirim',
        'delivered'                   => 'Diterima',
        'completed'                   => 'Selesai',
        'cancelled'                   => 'Dibatalkan',
        'refunded'                    => 'Dikembalikan',
    ];

    protected $fillable = [
        'order_number', 'user_id', 'shipping_address_id', 'fulfillment_method', 'status',
        'subtotal', 'shipping_cost', 'shipping_protection_opted', 'shipping_protection_fee', 'total_price', 'courier',
        'courier_service', 'tracking_number', 'notes',
        'paid_at', 'shipped_at', 'delivered_at',
        'discount_id', 'discount_amount',
        'promo_id', 'promo_discount_amount', 'bank_id', 'payment_channel',
        'level_discount_amount', 'loyalty_points_used', 'loyalty_discount_amount',
        'payment_proof_image', 'is_payment_verified', 'verified_by', 'payment_verified_at',
        'review_requested_at',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'shipping_cost'   => 'decimal:2',
        'shipping_protection_opted' => 'boolean',
        'shipping_protection_fee' => 'decimal:2',
        'total_price'     => 'decimal:2',
        'discount_amount'       => 'decimal:2',
        'promo_discount_amount' => 'decimal:2',
        'level_discount_amount' => 'decimal:2',
        'loyalty_discount_amount' => 'decimal:2',
        'paid_at'         => 'datetime',
        'shipped_at'      => 'datetime',
        'delivered_at'    => 'datetime',
        'review_requested_at' => 'datetime',
        'is_payment_verified' => 'boolean',
        'payment_verified_at' => 'datetime',
    ];

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function hasStatus(string $status): bool
    {
        return array_key_exists($status, self::STATUS_OPTIONS);
    }

    public static function statusTimestampPayload(string $status): array
    {
        return match ($status) {
            'shipped' => ['shipped_at' => now()],
            'delivered', 'completed' => ['delivered_at' => now()],
            default => [],
        };
    }

    protected static function booted(): void
    {
        static::created(function (self $order): void {
            OrderLog::create([
                'order_id' => $order->id,
                'event_type' => 'order_created',
                'current_status' => $order->status,
                'title' => 'Pesanan dibuat',
                'description' => 'Pesanan berhasil dibuat dan menunggu pembayaran.',
                'metadata' => [
                    'order_number' => $order->order_number,
                    'total_price' => (float) $order->total_price,
                ],
                'acted_by' => $order->user_id,
            ]);
        });

        static::updated(function (self $order): void {
            $actedBy = Auth::id();

            if ($order->wasChanged('status')) {
                if (in_array($order->status, ['paid', 'processing', 'shipped', 'completed'])) {
                    if ($order->payment && $order->payment->status !== 'success') {
                        $order->payment->update([
                            'status' => 'success',
                            'paid_at' => $order->paid_at ?? now(),
                        ]);
                    }
                } elseif ($order->status === 'cancelled') {
                    if ($order->payment && !in_array($order->payment->status, ['cancelled', 'failed', 'refund'])) {
                        $order->payment->update(['status' => 'cancelled']);
                    }
                }

                OrderLog::create([
                    'order_id' => $order->id,
                    'event_type' => 'status_changed',
                    'previous_status' => $order->getOriginal('status'),
                    'current_status' => $order->status,
                    'title' => 'Status pesanan diperbarui',
                    'description' => sprintf(
                        'Status pesanan berubah dari %s menjadi %s.',
                        $order->getOriginal('status') ?? '-',
                        $order->status ?? '-',
                    ),
                    'metadata' => [
                        'previous_status' => $order->getOriginal('status'),
                        'current_status' => $order->status,
                    ],
                    'acted_by' => $actedBy,
                ]);
            }

            if ($order->wasChanged('tracking_number') && filled($order->tracking_number)) {
                OrderLog::create([
                    'order_id' => $order->id,
                    'event_type' => 'tracking_updated',
                    'current_status' => $order->status,
                    'title' => 'Nomor resi diperbarui',
                    'description' => 'Nomor resi pengiriman telah diinput atau diperbarui.',
                    'metadata' => [
                        'tracking_number' => $order->tracking_number,
                    ],
                    'acted_by' => $actedBy,
                ]);
            }

            if ($order->wasChanged('payment_proof_image') && filled($order->payment_proof_image)) {
                OrderLog::create([
                    'order_id' => $order->id,
                    'event_type' => 'payment_proof_uploaded',
                    'current_status' => $order->status,
                    'title' => 'Bukti pembayaran diunggah',
                    'description' => 'Pelanggan telah mengunggah bukti pembayaran manual.',
                    'metadata' => [
                        'payment_proof_image' => $order->payment_proof_image,
                    ],
                    'acted_by' => $actedBy ?: $order->user_id,
                ]);
            }

            if ($order->wasChanged('is_payment_verified') && $order->is_payment_verified) {
                if ($order->payment && $order->payment->status !== 'success') {
                    $order->payment->update([
                        'status' => 'success',
                        'paid_at' => $order->payment_verified_at ?? now(),
                    ]);
                }
                if ($order->status === 'unpaid') {
                    $order->update([
                        'status' => 'paid',
                        'paid_at' => $order->payment_verified_at ?? now(),
                    ]);
                }

                OrderLog::create([
                    'order_id' => $order->id,
                    'event_type' => 'payment_verified',
                    'current_status' => $order->status,
                    'title' => 'Pembayaran diverifikasi',
                    'description' => 'Pembayaran pesanan telah diverifikasi.',
                    'metadata' => [
                        'payment_verified_at' => optional($order->payment_verified_at)?->toDateTimeString(),
                    ],
                    'acted_by' => $order->verified_by ?? $actedBy,
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(ShippingAddress::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function returnRequest(): HasOne
    {
        return $this->hasOne(ReturnRequest::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function complains(): HasMany
    {
        return $this->hasMany(Complain::class);
    }

    public function logs(): HasMany
    {
        // ORDER-005: Urutan ascending (kronologis) untuk tracking; gunakan ->latest() saat perlu terbaru dulu
        return $this->hasMany(OrderLog::class)->oldest();
    }
}
