<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password_hash',
        'phone_number',
        'role',
        'account_type',
        'gender',
        'email_verified',
        'account_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'removed_at' => 'datetime',
        'access_version' => 'integer',
        'email_verified' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function getRememberTokenName()
    {
        return '';
    }

    public function setRememberToken($value)
    {
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDriver(): bool
    {
        return $this->role === 'student' && $this->account_type === 'driver';
    }

    public function isPassenger(): bool
    {
        return $this->role === 'student' && $this->account_type === 'passenger';
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active' && $this->removed_at === null;
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'user_id', 'user_id');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class, 'driver_id', 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(RideBooking::class, 'passenger_id', 'user_id');
    }
}
