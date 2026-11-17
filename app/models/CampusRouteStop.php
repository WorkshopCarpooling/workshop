<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampusRouteStop extends Model
{
    use HasFactory;

    protected $table = 'campus_route_stops';
    protected $primaryKey = 'stop_id';
    public $timestamps = false;

    protected $fillable = [
        'route_id',
        'stop_order',
        'stop_name',
    ];

    protected $casts = [
        'stop_order' => 'integer',
        'created_at' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(CampusRoute::class, 'route_id', 'route_id');
    }

    public function rides()
    {
        return $this->belongsToMany(Ride::class, 'ride_route_stops', 'stop_id', 'ride_id');
    }

    public function bookings()
    {
        return $this->hasMany(RideBooking::class, 'pickup_stop_id', 'stop_id');
    }
}
