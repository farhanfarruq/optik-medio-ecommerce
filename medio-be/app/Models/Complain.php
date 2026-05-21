<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'complaint_type',
        'subject',
        'message',
        'contact_phone',
        'attachment_path',
        'status',
        'admin_notes',
        'resolved_at',
        'handled_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function getComplaintTypeLabelAttribute(): string
    {
        return match ($this->complaint_type) {
            'shipping_protection' => 'Klaim Proteksi Pengiriman',
            default => 'Komplain Umum',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
