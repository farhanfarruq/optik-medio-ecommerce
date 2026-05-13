<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEventLog extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'idempotency_key',
        'external_id',
        'status',
        'payload',
        'processing_status',
        'processing_note',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Cek apakah event ini sudah pernah diproses (idempotency check).
     */
    public static function alreadyProcessed(string $idempotencyKey): bool
    {
        return self::where('idempotency_key', $idempotencyKey)
            ->where('processing_status', 'processed')
            ->exists();
    }

    /**
     * Catat event baru dan return instance-nya.
     */
    public static function record(
        string $provider,
        string $externalId,
        string $status,
        array $payload,
        string $eventType = ''
    ): self {
        $key = $provider . ':' . $externalId . ':' . strtolower($status);

        return self::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'provider'          => $provider,
                'event_type'        => $eventType ?: strtoupper($status),
                'external_id'       => $externalId,
                'status'            => $status,
                'payload'           => $payload,
                'processing_status' => 'received',
            ]
        );
    }

    public function markProcessed(string $note = ''): void
    {
        $this->update([
            'processing_status' => 'processed',
            'processing_note'   => $note,
            'processed_at'      => now(),
        ]);
    }

    public function markSkipped(string $note = ''): void
    {
        $this->update([
            'processing_status' => 'skipped',
            'processing_note'   => $note,
            'processed_at'      => now(),
        ]);
    }

    public function markFailed(string $note = ''): void
    {
        $this->update([
            'processing_status' => 'failed',
            'processing_note'   => $note,
            'processed_at'      => now(),
        ]);
    }
}
