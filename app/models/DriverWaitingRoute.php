<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverWaitingRoute extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'available_until'=>'datetime', 'ride_id'=>'integer',
        'pickup_latitude'=>'float', 'pickup_longitude'=>'float',
        'dropoff_latitude'=>'float', 'dropoff_longitude'=>'float',
    ];
    public function driver() { return $this->belongsTo(User::class, 'driver_id', 'user_id'); }
    public function vehicle() { return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id'); }
    public function ride() { return $this->belongsTo(Ride::class, 'ride_id', 'ride_id'); }
}
