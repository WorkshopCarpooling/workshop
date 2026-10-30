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
        $query=Ride::where('ride_status','completed')->with(['vehicle','driver','bookings.passenger']);
        if ($user->isDriver()) $query->where('driver_id',$user->user_id);
        elseif (!$user->isAdmin()) $query->whereHas('bookings',fn($q)=>$q->where('passenger_id',$user->user_id)->where('booking_status','accepted'));
        $rides=$query->orderByDesc('completed_at')->limit(100)->get();
        $reports=$ready ? DB::table('ride_carbon_reports')->whereIn('ride_id',$rides->pluck('ride_id'))->get()->keyBy('ride_id') : collect();
        // Older completed trips get a read-only preview; visiting a page never writes reports.
        if ($ready) foreach ($rides as $ride) {
            if (!$reports->has($ride->ride_id) && ($preview=AutomaticCarbon::preview($ride))) $reports->put($ride->ride_id,$preview);
        }
        $bookings=$user->isPassenger() ? RideBooking::where('passenger_id',$user->user_id)->whereIn('booking_status',['pending','accepted'])->with('ride')->orderByDesc('created_at')->limit(100)->get() : collect();
        $scope=null;
        if ($user->isDriver()) $scope=Ride::where('driver_id',$user->user_id)->pluck('ride_id');
        elseif (!$user->isAdmin()) $scope=RideBooking::where('passenger_id',$user->user_id)->where('booking_status','accepted')->pluck('ride_id');
        $summary=CarbonEstimate::summary($scope);
        $emissions=$ready ? DB::table('ride_carbon_reports')->join('rides','rides.ride_id','=','ride_carbon_reports.ride_id')->where('rides.ride_status','completed') : null;
        if ($emissions && $scope !== null) $emissions->whereIn('rides.ride_id',$scope);
        $summary['emissions_count']=$emissions ? (clone $emissions)->count() : 0;
        $summary['emissions_total']=$emissions ? (clone $emissions)->sum('shared_kg') : 0;
        foreach ($reports as $report) if ($report->preview ?? false) {
            $summary['emissions_count']++;
            $summary['emissions_total']+=$report->shared_kg;
        }
        $impact=\App\Services\CarbonImpact::monthly($user,\App\Services\CarbonImpact::month(request()->query('month')));
        $carbonPreference=\App\Services\CarbonPreferences::forUser((int)$user->user_id);
        return response()->view('carbon',compact('user','ready','rides','reports','bookings','summary','impact','carbonPreference'))->header('Cache-Control','no-store, private');
    }

    public function baseline(Request $request, int $booking)
    {
        abort_unless(Auth::user()->isPassenger() && Auth::user()->isActive(),403);
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
        $data=$request->validate(CarbonEstimate::profileRules()+[
            'shared_km'=>'required|numeric|between:0.001,500', 'distance_basis'=>'required|in:odometer,road_estimate,planned_estimate',
            'driver_would_travel'=>'required|in:yes,no', 'driver_baseline_km'=>'nullable|numeric|between:0,500',
        ]);
        if ($data['driver_would_travel']==='yes') $request->validate(['driver_baseline_km'=>'required|numeric|between:0.001,500']);
        DB::transaction(function() use ($ride,$data) {
            $owned=Ride::where('ride_id',$ride)->where('driver_id',Auth::id())->where('ride_status','completed')->lockForUpdate()->with('bookings')->firstOrFail();
            $record=CarbonEstimate::calculate($owned,$data);
            $record['recorded_by']=Auth::id(); $record['updated_at']=now();
            $existing=DB::table('ride_carbon_reports')->where('ride_id',$ride)->first();
            if ($existing) DB::table('ride_carbon_reports')->where('ride_id',$ride)->update($record);
            else DB::table('ride_carbon_reports')->insert($record+['ride_id'=>$ride,'created_at'=>now()]);
        });
        return redirect('carbon')->with('carbonNotice','Trip emissions saved. Avoided emissions appear only when all alternative travel information is supported and complete.');
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
        abort_unless(Auth::user()->isAdmin() && Auth::user()->isActive(),403);
        abort_unless(CarbonEstimate::ready(),503,'Carbon storage is not ready.');
        return response()->streamDownload(function() {
            $out=fopen('php://output','w');
            fputcsv($out,['ride_id','completed_at','shared_km','distance_basis','shared_kg_co2e','baseline_kg_co2e','net_avoided_kg_co2e','needs_review','method','fuel','litres_per_100km','kg_co2e_per_litre','source_url']);
            $rows=DB::table('ride_carbon_reports')->join('rides','rides.ride_id','=','ride_carbon_reports.ride_id')->where('rides.ride_status','completed')->select('ride_carbon_reports.*','rides.completed_at')->orderBy('ride_carbon_reports.ride_id')->cursor();
            foreach($rows as $row) {
                $details=json_decode($row->details,true);
                fputcsv($out,[$row->ride_id,$row->completed_at,$row->shared_km,$row->distance_basis,$row->shared_kg,
                    $row->needs_review ? null : $row->baseline_kg,$row->needs_review ? null : $row->avoided_kg,(int)$row->needs_review,
                    $row->method,$details['fuel'] ?? '',$details['litres_per_100km'] ?? '',$details['fuel_factor'] ?? '',$details['source_url'] ?? '']);
            }
            fclose($out);
        },'rideutem-carbon-estimates.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store, private']);
    }
}
