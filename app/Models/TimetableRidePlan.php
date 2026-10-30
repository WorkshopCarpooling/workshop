<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableRidePlan extends Model
{
    protected $table = 'timetable_ride_plans';
    protected $fillable = [
        'user_id','timetable_id','pickup_label','pickup_latitude','pickup_longitude','dropoff_label',
        'dropoff_latitude','dropoff_longitude','seats_requested','arrival_buffer_minutes','driver_allowance_minutes',
        'travel_minutes','journey_distance_km','fare_per_seat','distance_source','fare_policy','starts_on','ends_on','is_enabled',
    ];
    protected $casts = [
        'pickup_latitude'=>'float','pickup_longitude'=>'float','dropoff_latitude'=>'float','dropoff_longitude'=>'float',
        'seats_requested'=>'integer','arrival_buffer_minutes'=>'integer','driver_allowance_minutes'=>'integer',
        'travel_minutes'=>'integer','journey_distance_km'=>'float','fare_per_seat'=>'decimal:2',
        'is_enabled'=>'boolean','starts_on'=>'date','ends_on'=>'date',
    ];
}
