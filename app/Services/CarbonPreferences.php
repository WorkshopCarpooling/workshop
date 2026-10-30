<?php
namespace App\Services;

use App\Models\RideBooking;
use Illuminate\Support\Facades\{DB,Schema};

final class CarbonPreferences
{
    public static function ready(): bool { return Schema::hasTable('carbon_travel_preferences'); }
    public static function forUser(int $user): ?object
    {
        return self::ready()?DB::table('carbon_travel_preferences')->where('user_id',$user)->first():null;
    }
    public static function apply(RideBooking $booking): void
    {
        // Explicit trip answers always take precedence over a saved preference.
        if(array_key_exists('baseline_mode',$booking->getAttributes()) && $booking->baseline_mode!==null) return;
        $preference=self::forUser((int)$booking->passenger_id);
        if(!$preference || !$preference->baseline_mode) return;
        if($preference->baseline_mode==='drive_alone' && $preference->baseline_cars>(int)$booking->seats_requested) return;
        foreach(['baseline_mode','baseline_cars','baseline_fuel_type','baseline_fuel_l_per_100km'] as $field) $booking->$field=$preference->$field;
        // Distance comes from this booking's own route; never reuse another trip's kilometres.
    }
}
