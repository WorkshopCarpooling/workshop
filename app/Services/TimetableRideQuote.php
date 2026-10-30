<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class TimetableRideQuote
{
    private static function fingerprint(object $session, array $inputs): string
    {
        $snapshot=[];
        foreach(['id','user_id','title','weekday','starts_at','ends_at','location','updated_at'] as $field) $snapshot[$field]=(string)$session->$field;
        return hash('sha256',json_encode([$snapshot,$inputs],JSON_THROW_ON_ERROR));
    }

    public static function issue(int $user, object $session, array $inputs, array $trip, array $timing): array
    {
        $fare=StudentFare::quote($trip['distanceMeters']/1000,null,$inputs['seats_requested']);
        $expires=now()->addMinutes((int)config('carpool.quote_minutes',10));
        $payload=['mode'=>'timetable','user'=>$user,'session'=>(int)$session->id,
            'fingerprint'=>self::fingerprint($session,$inputs),'inputs'=>$inputs,'timing'=>$timing,
            'fare'=>$fare,'source'=>$trip['source'],'expires'=>$expires->getTimestamp()];
        return $fare+['distanceSource'=>$trip['source'],'token'=>Crypt::encryptString(json_encode($payload,JSON_THROW_ON_ERROR)),
            'expiresAt'=>$expires->toIso8601String()];
    }

    public static function verify(string $token, int $user, object $session, array $inputs): array
    {
        try {
            $payload=json_decode(Crypt::decryptString($token),true,512,JSON_THROW_ON_ERROR);
            if($payload['mode']!=='timetable'||$payload['user']!==$user||$payload['session']!==(int)$session->id
                ||$payload['expires']<=now()->getTimestamp()||!hash_equals($payload['fingerprint'],self::fingerprint($session,$inputs))) throw new \RuntimeException();
            $fare=StudentFare::quote($payload['fare']['distanceKm'],null,$inputs['seats_requested']);
            if($fare!==$payload['fare']||\Carbon\Carbon::parse($payload['timing']['scheduledFor'])->lte(now())) throw new \RuntimeException();
            return $payload;
        } catch(\Throwable $error) {
            throw ValidationException::withMessages(['fare_quote'=>'Your timetable, journey or quote changed. Review this ride again before confirming.']);
        }
    }
}
