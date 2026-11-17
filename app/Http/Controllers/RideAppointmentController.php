<?php

namespace App\Http\Controllers;

use App\Models\RideRequest;
use App\Services\{FareQuote, Journey, OnDemandRides, RideAppointments};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RideAppointmentController extends Controller
{
    private function journey(Request $request): array
    {
        $fields = Journey::fields($request);
        $data = $request->validate([
            'pickup_date'=>'required|date_format:Y-m-d', 'pickup_time'=>'required|date_format:H:i',
            'seats_requested'=>'required|integer|between:1,3',
        ]);
        $time = Carbon::createFromFormat('!Y-m-d H:i', $data['pickup_date'].' '.$data['pickup_time'], config('app.timezone'));
        $request->merge(['scheduled_for'=>$time->format('Y-m-d H:i:s')]);
        $scheduled = $request->validate(['scheduled_for'=>'required|date|after:now']);
        return $fields + ['seats_requested'=>(int)$data['seats_requested']] + $scheduled;
    }

    private function appointments(): array
    {
        if (!RideAppointments::ready()) return [];
        return RideRequest::where('passenger_id', Auth::id())->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>=', now()->subDays(30))->with(['driver','ride'])
            ->orderBy('scheduled_for')->get()->map(fn ($request) => RideAppointments::payload($request))
            ->reject(fn ($appointment) => $appointment['status'] === 'cancelled')->values()->all();
    }

    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            $journey = $this->journey($request);
            $trip = (new RoutesController)->computeRoute(Request::create('/api/routes/compute', 'POST', [
                'origin_lat'=>$journey['pickup_latitude'], 'origin_lng'=>$journey['pickup_longitude'], 'origin_name'=>$journey['pickup_label'],
                'dest_lat'=>$journey['dropoff_latitude'], 'dest_lng'=>$journey['dropoff_longitude'], 'dest_name'=>$journey['dropoff_label'],
                'departure_at'=>$journey['scheduled_for'],
            ]))->getData(true);
            return response()->json(['fare'=>FareQuote::issueNow((int)Auth::id(), $journey, $trip, 'appointment'),
                'journey'=>$trip, 'scheduledFor'=>$journey['scheduled_for'], 'bookingReady'=>RideAppointments::ready()])
                ->header('Cache-Control','no-store, private');
        }
        RideAppointments::dispatchDue();
        return response()->view('schedule', ['appointments'=>$this->appointments(), 'appointmentReady'=>RideAppointments::ready()])
            ->header('Cache-Control','no-store, private');
    }

    public function store(Request $request)
    {
        abort_unless(RideAppointments::ready(), 503, 'Ride appointments are temporarily unavailable.');
        $journey = $this->journey($request);
        $quote = $request->validate(['fare_quote'=>'required|string|max:4096']);
        $fare = FareQuote::verifyNow($quote['fare_quote'], (int)Auth::id(), $journey, 'appointment');
        $appointment = RideAppointments::create((int)Auth::id(), $journey, $fare);
        if ($request->expectsJson()) return response()->json(['appointment'=>RideAppointments::payload($appointment), 'appointments'=>$this->appointments()], 201)
            ->header('Cache-Control','no-store, private');
        return redirect('passenger/schedule')->with('appointmentNotice','Appointment saved. Driver search will start at your selected time.');
    }

    public function status()
    {
        RideAppointments::dispatchDue();
        return response()->json(['appointments'=>$this->appointments()])->header('Cache-Control','no-store, private');
    }

    public function cancel(Request $request, int $id)
    {
        abort_unless(RideAppointments::ready(), 503);
        RideRequest::whereKey($id)->where('passenger_id',Auth::id())->whereNotNull('scheduled_for')->firstOrFail();
        OnDemandRides::cancel((int)Auth::id(), $id);
        if ($request->expectsJson()) return $this->status();
        return redirect('passenger/schedule')->with('appointmentNotice','Your appointment was cancelled.');
    }
}
