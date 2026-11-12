<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\RideBooking;
use App\Models\Vehicle;
use App\Services\CarbonEstimate;
use App\Services\AutomaticCarbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CarbonController extends Controller
{
    public function index()
    {
        $user=Auth::user(); $ready=CarbonEstimate::ready();
        $impact=\App\Services\CarbonImpact::monthly($user,\App\Services\CarbonImpact::month(request()->query('month')));
        return response()->view('carbon',compact('user','ready','impact'))->header('Cache-Control','no-store, private');
    }

    public function baseline(Request $request, int $booking)
    {
        abort_unless(Auth::user()->isPassenger() && Auth::user()->isActive(),403);
        abort_unless(config('carbon.legacy_comparisons_enabled',false),410,'Alternative-travel questions have been retired.');
        abort_unless(CarbonEstimate::ready(),503,'Carbon storage is not ready.');
        DB::transaction(function() use ($request,$booking) {
            $owned=RideBooking::where('booking_id',$booking)->where('passenger_id',Auth::id())->firstOrFail();
            $ride=Ride::where('ride_id',$owned->ride_id)->lockForUpdate()->firstOrFail();
            $owned=RideBooking::where('booking_id',$booking)->where('passenger_id',Auth::id())->whereIn('booking_status',['pending','accepted'])->lockForUpdate()->firstOrFail();
            $data=CarbonEstimate::baseline($request,$owned->seats_requested);
            $changed=false; foreach($data as $key=>$value) if ((string)$owned->$key !== (string)$value) $changed=true;
            $owned->update($data);
            if ($changed) {
                DB::table('ride_carbon_reports')->where('ride_id',$owned->ride_id)->update(['needs_review'=>true,'updated_at'=>now()]);
                AutomaticCarbon::refreshComparison($ride);
            }
        });
        return redirect('carbon')->with('carbonNotice','Alternative travel saved. Existing comparisons refresh automatically when their saved factors are still current.');
    }

    public function report(Request $request, int $ride)
    {
        abort_unless(Auth::user()->isDriver() && Auth::user()->isActive(),403);
        abort_unless(CarbonEstimate::ready(),503,'Carbon storage is not ready.');
        $legacy=config('carbon.legacy_comparisons_enabled',false)&&$request->has('driver_would_travel');
        $rules=CarbonEstimate::profileRules()+[
            'shared_km'=>'required|numeric|between:0.001,500', 'distance_basis'=>'required|in:odometer,road_estimate,planned_estimate',
        ];
        if($legacy)$rules+=['driver_would_travel'=>'required|in:yes,no','driver_baseline_km'=>'nullable|numeric|between:0,500'];
        $data=$request->validate($rules);
        if ($legacy&&$data['driver_would_travel']==='yes') $request->validate(['driver_baseline_km'=>'required|numeric|between:0.001,500']);
        DB::transaction(function() use ($ride,$data,$legacy) {
            $owned=Ride::where('ride_id',$ride)->where('driver_id',Auth::id())->where('ride_status','completed')->lockForUpdate()->with('bookings')->firstOrFail();
            $record=$legacy?CarbonEstimate::calculate($owned,$data):CarbonEstimate::footprint($owned,$data);
            $record['recorded_by']=Auth::id(); $record['updated_at']=now();
            $existing=DB::table('ride_carbon_reports')->where('ride_id',$ride)->first();
            if ($existing) DB::table('ride_carbon_reports')->where('ride_id',$ride)->update($record);
            else DB::table('ride_carbon_reports')->insert($record+['ride_id'=>$ride,'created_at'=>now()]);
        });
        return redirect('carbon')->with('carbonNotice','Trip footprint saved. Emissions are allocated equally across the driver and accepted passenger seats.');
    }

    public function vehicle(Request $request, int $vehicle)
    {
        abort_unless(Auth::user()->isDriver() && Auth::user()->isActive(),403);
        abort_unless(CarbonEstimate::ready(),503,'Carbon storage is not ready.');
        $data=$request->validate(CarbonEstimate::profileRules());
        Vehicle::where('vehicle_id',$vehicle)->where('user_id',Auth::id())->firstOrFail()->update($data);
        return redirect('driver/vehicles?vehicle='.$vehicle)->with('driver_notice','Fuel profile saved. Existing carbon snapshots keep their recorded fuel assumptions.')->with('driver_notice_type','success');
    }

    public function export()
    {
        return app(CarbonToolsController::class)->download();
    }
}
