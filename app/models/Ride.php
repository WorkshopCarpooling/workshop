<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    protected $table = 'rides';
    protected $primaryKey = 'ride_id';

    protected $fillable = [
        'navigation_plan',
        'pool_seat_capacity', 'pool_origin_latitude', 'pool_origin_longitude',
        'ride_mode',
        'pickup_latitude', 'pickup_longitude', 'dropoff_latitude', 'dropoff_longitude', 'started_at', 'completed_at',
        'driver_id',
        'vehicle_id',
        'route_id',
        'pickup_point',
        'destination',
        'departure_at',
        'available_seats',
        'contribution_per_seat',
        'vehicle_description',
        'vehicle_type',
        'vehicle_color',
        'vehicle_plate',
        'notes',
        'ride_status',
    ];

    protected $casts = [
        'navigation_plan'=>'array',
        'pool_seat_capacity'=>'integer', 'pool_origin_latitude'=>'float', 'pool_origin_longitude'=>'float',
        'dropoff_latitude' => 'float', 'dropoff_longitude' => 'float',
        'pickup_latitude' => 'float', 'pickup_longitude' => 'float', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        'departure_at' => 'datetime',
        'available_seats' => 'integer',
        'contribution_per_seat' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id', 'user_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function route()
    {
        return $this->belongsTo(CampusRoute::class, 'route_id', 'route_id');
    }

    public function pickupStops()
    {
        return $this->belongsToMany(CampusRouteStop::class, 'ride_route_stops', 'ride_id', 'stop_id')
                    ->orderBy('stop_order');
    }

    public function bookings()
    {
        return $this->hasMany(RideBooking::class, 'ride_id', 'ride_id');
    }
}
