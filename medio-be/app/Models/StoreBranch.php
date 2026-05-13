<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreBranch extends Model
{
    protected $fillable = [
        'name', 'code', 'address', 'city', 'province',
        'phone', 'email', 'maps_url',
        'latitude', 'longitude',
        'operating_hours', 'appointment_capacity', 'is_active',
    ];

    protected $casts = [
        'operating_hours'      => 'array',
        'appointment_capacity' => 'integer',
        'is_active'            => 'boolean',
        'latitude'             => 'float',
        'longitude'            => 'float',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'branch_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BranchSchedule::class, 'branch_id');
    }

    /**
     * Cek kapasitas tersisa untuk tanggal tertentu.
     */
    public function availableCapacity(\Carbon\Carbon $date): int
    {
        $schedule = BranchSchedule::where('branch_id', $this->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($schedule?->is_closed) {
            return 0;
        }

        $capacity = $schedule?->capacity_override ?? $this->appointment_capacity;

        $booked = Appointment::where('branch_id', $this->id)
            ->whereDate('appointment_date', $date->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->count();

        return max(0, $capacity - $booked);
    }
}
