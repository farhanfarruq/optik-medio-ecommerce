<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessEvent extends Model
{
    // Event type constants
    const PRODUCT_VIEWED       = 'product_viewed';
    const ADD_TO_CART          = 'add_to_cart';
    const CHECKOUT_STARTED     = 'checkout_started';
    const SHIPPING_SELECTED    = 'shipping_selected';
    const PAYMENT_SELECTED     = 'payment_selected';
    const ORDER_CREATED        = 'order_created';
    const PAYMENT_SUCCESS      = 'payment_success';
    const ORDER_CANCELLED      = 'order_cancelled';
    const COMPLAINT_CREATED    = 'complaint_created';
    const RETURN_REQUESTED     = 'return_requested';
    const SEARCH_NO_RESULT     = 'search_no_result';
    const CHECKOUT_FAILED      = 'checkout_failed';
    const FILTER_USED          = 'filter_used';

    protected $fillable = [
        'event_type',
        'user_id',
        'session_id',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Catat business event secara fire-and-forget.
     * Tidak throw exception agar tidak mengganggu flow utama.
     */
    public static function record(
        string $eventType,
        array $payload = [],
        ?int $userId = null,
        ?string $sessionId = null
    ): void {
        try {
            static::create([
                'event_type' => $eventType,
                'user_id'    => $userId ?? auth()->id(),
                'session_id' => $sessionId,
                'payload'    => $payload ?: null,
                'ip_address' => request()->ip(),
                'user_agent' => substr(request()->userAgent() ?? '', 0, 255),
            ]);
        } catch (\Throwable) {
            // Observability tidak boleh merusak flow utama
        }
    }
}
