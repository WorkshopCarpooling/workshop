<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampusRoute extends Model
{
    use HasFactory;

    protected $table = 'campus_routes';
    protected $primaryKey = 'route_id';
    public $timestamps = false;

    protected $fillable = [
        'route_code',
        'route_name',
        'direction',
        'origin_name',
        'destination_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function stops()
    {
        return $this->hasMany(CampusRouteStop::class, 'route_id', 'route_id')->orderBy('stop_order');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class, 'route_id', 'route_id');
    }
}
