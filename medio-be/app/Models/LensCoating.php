<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LensCoating extends Model
{
    protected $fillable = [
        'name',
        'price',
        'description',
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'is_active' => 'boolean',
    ];
}
