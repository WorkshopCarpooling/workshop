<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripAssistanceReport extends Model
{
    public const SIMULATION_SECONDS = 25;

    public const REASONS = [
        'breakdown' => 'Vehicle breakdown',
        'flat_tyre' => 'Flat tyre',
        'other' => 'Other roadside issue',
    ];

    protected $fillable = [
        'ride_id', 'reason', 'latitude', 'longitude', 'position_source', 'position_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'position_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'mechanic_route' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class, 'ride_id', 'ride_id');
    }

    public function status(): string
    {
        if ($this->resolved_at) {
            return 'resolved';
        }
        if (!$this->dispatched_at) {
            return 'waiting';
        }

        return now()->gte($this->dispatched_at->copy()->addSeconds(self::SIMULATION_SECONDS))
            ? 'arrived'
            : 'en_route';
    }
}
