<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideBooking extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::creating(fn (self $booking) => \App\Services\CarbonPreferences::apply($booking));
    }

    protected $table = 'ride_bookings';
    protected $primaryKey = 'booking_id';

    protected $fillable = [
        'baseline_mode', 'baseline_fuel_type', 'baseline_fuel_l_per_100km', 'baseline_cars', 'baseline_distance_km',
        'fare_per_seat', 'journey_distance_km', 'distance_source', 'fare_policy',
        'pickup_latitude', 'pickup_longitude', 'dropoff_latitude', 'dropoff_longitude', 'dropoff_label', 'arrive_by', 'compatibility_score',
        'ride_id',
        'passenger_id',
        'pickup_stop_id',
        'seats_requested',
        'pickup_label',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_accuracy_m',
        'booking_status',
    ];

    protected $casts = [
        'picked_up_at'=>'datetime', 'dropped_off_at'=>'datetime',
        'baseline_fuel_l_per_100km'=>'float', 'baseline_cars'=>'integer', 'baseline_distance_km'=>'float',
        'fare_per_seat' => 'decimal:2', 'journey_distance_km' => 'float',
        'dropoff_latitude' => 'float', 'dropoff_longitude' => 'float',
        'arrive_by' => 'datetime',
        'seats_requested' => 'integer',
        'pickup_latitude' => 'float',
        'pickup_longitude' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class, 'ride_id', 'ride_id');
    }

    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id', 'user_id');
    }

    public function pickupStop()
    {
        return $this->belongsTo(CampusRouteStop::class, 'pickup_stop_id', 'stop_id');
    }
}
