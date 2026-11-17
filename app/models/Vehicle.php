<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $table = 'vehicles';
    protected $primaryKey = 'vehicle_id';

    protected $fillable = [
        'fuel_type', 'fuel_l_per_100km',
        'user_id',
        'vehicle_type',
        'vehicle_color',
        'vehicle_plate',
        'verification_status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'fuel_l_per_100km' => 'float',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class, 'vehicle_id', 'vehicle_id');
    }
}
