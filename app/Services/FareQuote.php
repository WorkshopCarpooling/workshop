<?php

namespace App\Services;

use App\Models\Ride;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class FareQuote
{
    private static function fingerprint(array $journey): string
    {
        $values = [];
        foreach (['pickup_latitude', 'pickup_longitude', 'dropoff_latitude', 'dropoff_longitude'] as $key) $values[$key] = number_format((float)$journey[$key], 7, '.', '');
        foreach (['pickup_label', 'dropoff_label', 'departure_at', 'arrive_by', 'scheduled_for'] as $key) $values[$key] = (string)($journey[$key] ?? '');
        $values['seats_requested'] = (int)$journey['seats_requested'];
        return hash('sha256', json_encode($values));
    }

    public static function issue(int $user, Ride $ride, array $journey, array $trip): array
    {
        $fare = StudentFare::quote($trip['distanceMeters'] / 1000, (float)$ride->contribution_per_seat, (int)$journey['seats_requested']);
        $expires = now()->addMinutes((int)config('carpool.quote_minutes', 10));
        $token = Crypt::encryptString(json_encode([
            'user' => $user, 'ride' => (int)$ride->ride_id, 'journey' => self::fingerprint($journey),
            'meters' => (int)$trip['distanceMeters'], 'source' => $trip['source'],
            'fare' => $fare, 'expires' => $expires->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
        return $fare + ['token' => $token, 'expiresAt' => $expires->toIso8601String(), 'distanceSource' => $trip['source']];
    }

    public static function verify(string $token, int $user, Ride $ride, array $journey): array
    {
        try {
            $encoded = base64_decode($token, true);
            if ($encoded === false || !hash_equals(base64_encode($encoded), $token)) throw new \RuntimeException();
            $q = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
            if ($q['user'] !== $user || $q['ride'] !== (int)$ride->ride_id || $q['expires'] <= now()->getTimestamp() || !hash_equals($q['journey'], self::fingerprint($journey))) throw new \RuntimeException();
            $fare = StudentFare::quote($q['meters']/1000, (float)$ride->contribution_per_seat, (int)$journey['seats_requested']);
            if ($fare !== $q['fare']) throw new \RuntimeException();
            return ['fare_per_seat' => $fare['perSeat'], 'journey_distance_km' => $fare['distanceKm'], 'distance_source' => $q['source'], 'fare_policy' => $fare['policy']];
        } catch (\Throwable $error) {
            throw ValidationException::withMessages(['fare_quote' => 'Your fare quote expired or the journey changed. Search again before requesting a seat.']);
        }
    }

    public static function issueNow(int $user, array $journey, array $trip, string $mode = 'now'): array
    {
        $fare = StudentFare::quote($trip['distanceMeters'] / 1000, null, (int)$journey['seats_requested']);
        $expires = now()->addMinutes((int)config('carpool.quote_minutes', 10));
        $token = Crypt::encryptString(json_encode([
            'mode'=>$mode, 'user'=>$user, 'journey'=>self::fingerprint($journey),
            'meters'=>(int)$trip['distanceMeters'], 'source'=>$trip['source'],
            'fare'=>$fare, 'expires'=>$expires->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
        return $fare + ['token'=>$token, 'expiresAt'=>$expires->toIso8601String(), 'distanceSource'=>$trip['source']];
    }

    public static function verifyNow(string $token, int $user, array $journey, string $mode = 'now'): array
    {
        try {
            $encoded = base64_decode($token, true);
            if ($encoded === false || !hash_equals(base64_encode($encoded), $token)) throw new \RuntimeException();
            $quote = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
            if (($quote['mode'] ?? '') !== $mode || $quote['user'] !== $user || $quote['expires'] <= now()->getTimestamp()
                || !hash_equals($quote['journey'], self::fingerprint($journey))) throw new \RuntimeException();
            $fare = StudentFare::quote($quote['meters']/1000, null, (int)$journey['seats_requested']);
            if ($fare !== $quote['fare']) throw new \RuntimeException();
            return ['fare_per_seat'=>$fare['perSeat'], 'journey_distance_km'=>$fare['distanceKm'],
                'distance_source'=>$quote['source'], 'fare_policy'=>$fare['policy']];
        } catch (\Throwable $error) {
            throw ValidationException::withMessages(['fare_quote'=>'Your fare quote expired or your journey changed. Search again before requesting a ride.']);
        }
    }
}
