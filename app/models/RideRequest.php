<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideRequest extends Model
{
    protected $table = 'ride_requests';
    protected $primaryKey = 'request_id';
    protected $fillable = [
        'timetable_plan_id','timetable_entry_id','timetable_session_title','timetable_session_at',
        'passenger_id', 'driver_id', 'ride_id', 'pickup_label', 'pickup_latitude', 'pickup_longitude',
        'dropoff_label', 'dropoff_latitude', 'dropoff_longitude', 'seats_requested', 'fare_per_seat',
        'journey_distance_km', 'distance_source', 'fare_policy', 'request_status', 'expires_at', 'accepted_at', 'scheduled_for',
    ];
    protected $casts = [
        'timetable_session_at'=>'datetime',
        'pickup_latitude'=>'float', 'pickup_longitude'=>'float', 'dropoff_latitude'=>'float', 'dropoff_longitude'=>'float',
        'seats_requested'=>'integer', 'fare_per_seat'=>'decimal:2', 'journey_distance_km'=>'float',
        'expires_at'=>'datetime', 'accepted_at'=>'datetime', 'scheduled_for'=>'datetime',
    ];
    public function passenger() { return $this->belongsTo(User::class, 'passenger_id', 'user_id'); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id', 'user_id'); }
    public function ride() { return $this->belongsTo(Ride::class, 'ride_id', 'ride_id'); }

    public function displayStatus(): string
    {
        if ($this->request_status === 'pending' && $this->expires_at->lte(now())) return 'expired';
        if ($this->request_status === 'accepted') {
            return match ($this->ride?->ride_status) {
                'in_progress' => 'in_progress', 'completed' => 'completed', 'cancelled' => 'cancelled',
                default => 'accepted',
            };
        }
        return $this->request_status;
    }
}
