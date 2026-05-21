<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Appointment extends Model
{
    protected $fillable = [
        'appointment_number', 'user_id', 'branch_id',
        'appointment_date', 'appointment_time', 'service_type',
        'status', 'customer_name', 'customer_phone', 'notes',
        'admin_notes', 'order_id',
        'confirmed_at', 'completed_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'appointment_date' => 'date:Y-m-d',
        'confirmed_at'     => 'datetime',
        'completed_at'     => 'datetime',
        'cancelled_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(StoreBranch::class, 'branch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Generate nomor appointment unik.
     */
    public static function generateNumber(): string
    {
        return 'APT-' . strtoupper(Str::random(8));
    }

    /**
     * Label human-readable untuk service type.
     */
    public function getServiceLabelAttribute(): string
    {
        return match ($this->service_type) {
            'eye_test'        => 'Tes Mata',
            'pickup'          => 'Ambil Pesanan',
            'fitting'         => 'Fitting Frame',
            'consultation'    => 'Konsultasi',
            'lens_replacement'=> 'Ganti Lensa',
            default           => ucfirst(str_replace('_', ' ', $this->service_type)),
        };
    }
}
