<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\TripAssistanceReport;
use App\Services\DriverLocation;
use App\Services\NavigationRoad;
use App\Services\TripTest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TripAssistanceController extends Controller
{
    private const WORKSHOP = [2.3165, 102.3208];

    public function status(Request $request, int $ride)
    {
        $user = $request->user();
        abort_unless($user?->isActive() && ($user->isDriver() || $user->isPassenger()), 403);
        Ride::whereKey($ride)->when($user->isDriver(),
            fn ($query) => $query->where('driver_id', $user->getKey()),
            fn ($query) => $query->whereHas('bookings', fn ($booking) => $booking
                ->where('passenger_id', $user->getKey())->where('booking_status', 'accepted'))
        )->firstOrFail();

        $report = TripAssistanceReport::where('ride_id', $ride)->latest('id')->first();

        return $this->privateJson(['status' => $report?->status()]);
    }

    public function report(Request $request, int $ride)
    {
        $driver = $request->user();
        abort_unless($driver?->isDriver() && $driver->isActive(), 403);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(TripAssistanceReport::REASONS))]]);

        $report = DB::transaction(function () use ($driver, $ride, $data) {
            $trip = Ride::whereKey($ride)->where('driver_id', $driver->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($trip->ride_status === 'in_progress', 409, 'Start the trip before reporting a problem.');

            $existing = TripAssistanceReport::where('ride_id', $ride)->whereNull('resolved_at')->first();
            if ($existing) {
                return $existing;
            }

            return TripAssistanceReport::create([
                'ride_id' => $ride,
                'reason' => $data['reason'],
            ] + $this->reportedPosition($trip));
        }, 3);

        return $this->privateJson(['status' => $report->status()]);
    }

    public function adminFeed(Request $request)
    {
        $this->requireAdmin($request);

        return $this->privateJson($this->adminPayload());
    }

    public function adminUpdate(Request $request, int $report)
    {
        $this->requireAdmin($request);
        $action = $request->validate(['action' => ['required', Rule::in(['dispatch', 'resolve'])]])['action'];
        $route = $action === 'dispatch' ? $this->mechanicRoute($report) : null;

        DB::transaction(function () use ($action, $report, $route) {
            $incident = TripAssistanceReport::whereKey($report)->lockForUpdate()->firstOrFail();
            if ($incident->resolved_at) {
                abort_unless($action === 'resolve', 409, 'The report is already closed.');
                return;
            }
            if ($action === 'resolve') {
                $incident->resolved_at = now();
            } elseif (!$incident->dispatched_at) {
                abort_if($incident->latitude === null || $incident->longitude === null,
                    422, 'A trip location is needed to plan an assistance route.');
                $incident->mechanic_route = $route;
                $incident->dispatched_at = now();
            }
            $incident->save();
        }, 3);

        return $this->privateJson($this->adminPayload());
    }

    private function mechanicRoute(int $report): ?array
    {
        $incident = TripAssistanceReport::findOrFail($report);
        abort_if($incident->resolved_at, 409, 'The report is already closed.');
        if ($incident->dispatched_at) {
            return null;
        }
        abort_if($incident->latitude === null || $incident->longitude === null,
            422, 'A trip location is needed to plan an assistance route.');

        $road = NavigationRoad::route([
            ['lat' => self::WORKSHOP[0], 'lng' => self::WORKSHOP[1]],
            ['lat' => $incident->latitude, 'lng' => $incident->longitude],
        ]);

        return ['path' => $road['path'], 'road' => $road['road']];
    }

    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin() && $request->user()->isActive(), 403);
    }

    private function adminPayload(): array
    {
        $reports = TripAssistanceReport::whereNull('resolved_at')->with('ride.driver')->latest('id')->get();

        return [
            'serverTime' => now()->getTimestampMs(),
            'simulationSeconds' => TripAssistanceReport::SIMULATION_SECONDS,
            'workshop' => self::WORKSHOP,
            'reports' => $reports->map(fn (TripAssistanceReport $report) => [
                'id' => $report->id,
                'rideId' => $report->ride_id,
                'driver' => $report->ride->driver?->full_name ?? 'Driver',
                'plate' => $report->ride->vehicle_plate,
                'reason' => TripAssistanceReport::REASONS[$report->reason],
                'status' => $report->status(),
                'position' => $report->latitude === null ? null : [$report->latitude, $report->longitude],
                'positionSource' => $report->position_source,
                'positionAt' => $report->position_at?->toIso8601String(),
                'dispatchedAt' => $report->dispatched_at?->getTimestampMs(),
                'route' => $report->mechanic_route,
            ])->values(),
        ];
    }

    private function reportedPosition(Ride $trip): array
    {
        $simulation = TripTest::movement($trip);
        if ($simulation) {
            return [
                'latitude' => $simulation['location']['lat'],
                'longitude' => $simulation['location']['lng'],
                'position_source' => 'simulation',
                'position_at' => now(),
            ];
        }

        $fix = DriverLocation::get($trip->getKey());
        if (!isset($fix['latitude'], $fix['longitude'], $fix['capturedAt'])
            || now()->timestamp - $fix['capturedAt'] > 600) {
            return [];
        }

        return [
            'latitude' => $fix['latitude'],
            'longitude' => $fix['longitude'],
            'position_source' => 'gps',
            'position_at' => Carbon::createFromTimestamp((int) $fix['capturedAt'], config('app.timezone')),
        ];
    }

    private function privateJson(array $data)
    {
        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }
}
