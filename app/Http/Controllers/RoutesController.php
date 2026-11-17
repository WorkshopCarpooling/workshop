<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\StudentFare;

class RoutesController extends Controller
{
    /**
     * Known campus corridor coordinates in Melaka for RideUTeM.
     */
    public static function getCampusLocations(): array
    {
        return [
            'Melaka Sentral' => ['lat' => 2.2214, 'lng' => 102.2504],
            'Opposite SK Batu Berendam 2' => ['lat' => 2.2512, 'lng' => 102.2591],
            'Mydin MITC' => ['lat' => 2.2707, 'lng' => 102.2856],
            'Zoo Melaka' => ['lat' => 2.2768, 'lng' => 102.2996],
            'UTeM Main Campus (Durian Tunggal)' => ['lat' => 2.3139, 'lng' => 102.3183],
            'UTeM Main Campus' => ['lat' => 2.3139, 'lng' => 102.3183],
        ];
    }

    /**
     * Compute route between origin and destination using Google Routes API v2.
     */
    public function computeRoute(Request $request)
    {
        $request->validate([
            'origin_lat' => 'required|numeric|between:-90,90', 'origin_lng' => 'required|numeric|between:-180,180',
            'dest_lat' => 'required|numeric|between:-90,90', 'dest_lng' => 'required|numeric|between:-180,180',
            'origin_name' => 'nullable|string|max:180', 'dest_name' => 'nullable|string|max:180',
            'intermediates' => 'nullable|array|max:12', 'intermediates.*.lat' => 'required|numeric|between:-90,90',
            'intermediates.*.lng' => 'required|numeric|between:-180,180',
            'departure_at' => 'nullable|date|after:now',
        ]);
        $locations = self::getCampusLocations();

        $originLat = filter_var($request->input('origin_lat'), FILTER_VALIDATE_FLOAT);
        $originLng = filter_var($request->input('origin_lng'), FILTER_VALIDATE_FLOAT);
        $originName = trim((string) $request->input('origin_name', ''));

        $destLat = filter_var($request->input('dest_lat'), FILTER_VALIDATE_FLOAT);
        $destLng = filter_var($request->input('dest_lng'), FILTER_VALIDATE_FLOAT);
        $destName = trim((string) $request->input('dest_name', 'UTeM Main Campus (Durian Tunggal)'));

        if (($originLat === false || $originLng === false) && isset($locations[$originName])) {
            $originLat = $locations[$originName]['lat'];
            $originLng = $locations[$originName]['lng'];
        }

        if (($destLat === false || $destLng === false) && isset($locations[$destName])) {
            $destLat = $locations[$destName]['lat'];
            $destLng = $locations[$destName]['lng'];
        }

        if ($originLat === false || $originLng === false) {
            $originLat = 2.2214;
            $originLng = 102.2504;
        }

        if ($destLat === false || $destLng === false) {
            $destLat = 2.3139;
            $destLng = 102.3183;
        }

        $apiKey = (string) config('services.google_maps.api_key', '');

        if ($apiKey !== '') {
            try {
                $payload = [
                    'origin' => [
                        'location' => [
                            'latLng' => [
                                'latitude' => (float) $originLat,
                                'longitude' => (float) $originLng,
                            ],
                        ],
                    ],
                    'destination' => [
                        'location' => [
                            'latLng' => [
                                'latitude' => (float) $destLat,
                                'longitude' => (float) $destLng,
                            ],
                        ],
                    ],
                    'travelMode' => 'DRIVE',
                    'routingPreference' => 'TRAFFIC_AWARE',
                    'computeAlternativeRoutes' => false,
                    'languageCode' => 'en-MY',
                    'units' => 'METRIC',
                ];

                $payload['intermediates'] = array_map(fn ($point) => ['location' => ['latLng' => ['latitude' => (float)$point['lat'], 'longitude' => (float)$point['lng']]]], $request->input('intermediates', []));
                if ($request->filled('departure_at')) $payload['departureTime'] = \Carbon\Carbon::parse($request->input('departure_at'))->utc()->toRfc3339String();
                $route = Cache::remember('student-road:' . sha1($apiKey . json_encode($payload) . now()->format('YmdHi')), 120, function () use ($apiKey, $payload) {
                    $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.legs.duration,routes.legs.distanceMeters',
                    ])->timeout(6)->post('https://routes.googleapis.com/directions/v2:computeRoutes', $payload)->throw();
                    return $response->json('routes.0');
                });

                    if ($route) {
                        $distanceMeters = (int) ($route['distanceMeters'] ?? 0);
                        $durationSeconds = (int) rtrim((string) ($route['duration'] ?? '0s'), 's');
                        $encodedPolyline = (string) ($route['polyline']['encodedPolyline'] ?? '');

                        return response()->json([
                            'source' => 'google_routes_api',
                            'origin' => ['lat' => (float) $originLat, 'lng' => (float) $originLng, 'name' => $originName],
                            'destination' => ['lat' => (float) $destLat, 'lng' => (float) $destLng, 'name' => $destName],
                            'distanceMeters' => $distanceMeters,
                            'distanceText' => number_format($distanceMeters / 1000, 1) . ' km',
                            'fare' => StudentFare::quote($distanceMeters / 1000),
                            'durationSeconds' => $durationSeconds,
                            'durationText' => max(1, (int) round($durationSeconds / 60)) . ' mins',
                            'encodedPolyline' => $encodedPolyline,
                            'legs' => $route['legs'] ?? [],
                            'path' => $encodedPolyline !== '' ? self::decodePolyline($encodedPolyline) : [],
                        ]);
                    }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // A transparent estimate through the requested points, never a fabricated road path.
        $path = array_merge([['lat' => (float)$originLat, 'lng' => (float)$originLng]], $request->input('intermediates', []), [['lat' => (float)$destLat, 'lng' => (float)$destLng]]);
        $roadKm = 0;
        for ($i=1; $i<count($path); $i++) $roadKm += self::haversineDistance($path[$i-1]['lat'], $path[$i-1]['lng'], $path[$i]['lat'], $path[$i]['lng']) * 1.28;
        $roadKm = round($roadKm, 2);
        $distanceMeters = (int)round($roadKm * 1000);
        $durationMinutes = max(1, (int)round($roadKm / 42 * 60));

        return response()->json([
            'source' => $apiKey !== '' ? 'google_routes_fallback' : 'corridor_estimate',
            'origin' => ['lat' => (float) $originLat, 'lng' => (float) $originLng, 'name' => $originName],
            'destination' => ['lat' => (float) $destLat, 'lng' => (float) $destLng, 'name' => $destName],
            'distanceMeters' => $distanceMeters,
            'distanceText' => number_format($roadKm, 1) . ' km',
            'fare' => StudentFare::quote($distanceMeters / 1000),
            'durationSeconds' => $durationMinutes * 60,
            'durationText' => $durationMinutes . ' mins',
            'encodedPolyline' => '',
            'path' => $path,
        ]);
    }

    /**
     * Decode a Google encoded polyline string into [{lat, lng}, ...].
     */
    public static function decodePolyline(string $encoded): array
    {
        $length = strlen($encoded);
        $index = 0;
        $points = [];
        $lat = 0;
        $lng = 0;

        while ($index < $length) {
            $b = 0;
            $shift = 0;
            $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20 && $index < $length);
            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            $shift = 0;
            $result = 0;
            do {
                if ($index >= $length) break;
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20 && $index < $length);
            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $points[] = [
                'lat' => $lat * 1e-5,
                'lng' => $lng * 1e-5,
            ];
        }

        return $points;
    }

    protected static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    protected static function buildCorridorPath(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $stops = [
            ['lat' => 2.2214, 'lng' => 102.2504], // Melaka Sentral
            ['lat' => 2.2512, 'lng' => 102.2591], // Batu Berendam
            ['lat' => 2.2707, 'lng' => 102.2856], // Mydin MITC
            ['lat' => 2.2768, 'lng' => 102.2996], // Zoo Melaka
            ['lat' => 2.3139, 'lng' => 102.3183], // UTeM Main Campus
        ];

        $goingToCampus = $destLat >= $originLat;
        if (!$goingToCampus) {
            $stops = array_reverse($stops);
        }

        $path = [['lat' => $originLat, 'lng' => $originLng]];
        foreach ($stops as $stop) {
            if ($goingToCampus) {
                if ($stop['lat'] > $originLat + 0.002 && $stop['lat'] < $destLat - 0.002) {
                    $path[] = $stop;
                }
            } else {
                if ($stop['lat'] < $originLat - 0.002 && $stop['lat'] > $destLat + 0.002) {
                    $path[] = $stop;
                }
            }
        }
        $path[] = ['lat' => $destLat, 'lng' => $destLng];

        return $path;
    }
}
