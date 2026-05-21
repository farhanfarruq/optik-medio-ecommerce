<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionProfile extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'lens_type',
        'right_sphere',
        'right_cylinder',
        'right_axis',
        'right_add',
        'left_sphere',
        'left_cylinder',
        'left_axis',
        'left_add',
        'pd_single',
        'pd_right',
        'pd_left',
        'notes',
        'admin_notes',
        'verification_status',
        'attachment_path',
        'verified_by',
        'verified_at',
        'is_default',
    ];

    protected $casts = [
        'right_sphere' => 'float',
        'right_cylinder' => 'float',
        'right_axis' => 'integer',
        'right_add' => 'float',
        'left_sphere' => 'float',
        'left_cylinder' => 'float',
        'left_axis' => 'integer',
        'left_add' => 'float',
        'pd_single' => 'float',
        'pd_right' => 'float',
        'pd_left' => 'float',
        'verified_at' => 'datetime',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
