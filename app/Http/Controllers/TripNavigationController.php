<?php
namespace App\Http\Controllers;
use App\Models\Ride;
use App\Services\{TripNavigation,NavigationRoad,LiveCarbon};
use Illuminate\Http\Request;

class TripNavigationController extends Controller {
    private function owned(Request $request,int $id): Ride {
        $user=$request->user();abort_unless($user&&$user->isActive()&&$user->role==='student',403);
        return Ride::whereKey($id)->where(function($q)use($user){
            if($user->isDriver())$q->where('driver_id',$user->user_id);
            else $q->whereHas('bookings',fn($b)=>$b->where('passenger_id',$user->user_id)->where('booking_status','accepted'));
        })->with('bookings')->firstOrFail();
    }
    public function show(Request $request,int $ride){
        abort_unless(TripNavigation::ready(),503,'Trip navigation is temporarily unavailable.');
        abort_if($request->has('demo'),404);
        $ride=$this->owned($request,$ride);
        if($request->user()->isDriver()&&\App\Services\TripTest::enabled()&&in_array($ride->ride_status,['scheduled','in_progress'],true))
            return redirect('trips/'.$ride->ride_id.'/test')->header('Cache-Control','no-store, private');
        if($ride->ride_status!=='in_progress')return redirect($this->returnUrl($request))->header('Cache-Control','no-store, private');
        $payload=$this->payload($ride,$request,true);$navigation=$payload['navigation'];
        abort_unless(count($navigation['stops'])>1,422,'This journey has no usable route coordinates.');
        $road=$payload['route'];
        return response()->view('trip-navigation',compact('ride','navigation','road'))->header('Cache-Control','no-store, private');
    }
    public function status(Request $request,int $ride){
        abort_unless(TripNavigation::ready(),503);$owned=$this->owned($request,$ride);
        if($owned->ride_status!=='in_progress')return response()->json(['ended'=>true,'redirectUrl'=>$this->returnUrl($request)])->header('Cache-Control','no-store, private');
        $payload=$this->payload($owned,$request,$request->boolean('route'));
        return response()->json($payload)->header('Cache-Control','no-store, private');
    }
    public function advance(Request $request,int $ride){
        $owned=$this->owned($request,$ride);abort_unless((int)$owned->driver_id===(int)$request->user()->user_id,403);
        $data=$request->validate(['stop_key'=>'required|string|max:180']);
        TripNavigation::advance((int)$request->user()->user_id,$ride,$data['stop_key']);return $this->status($request,$ride);
    }
    private function returnUrl(Request $request): string {return url($request->user()->isDriver()?'driver/schedule':'passenger/bookings');}
    private function payload(Ride $ride,Request $request,bool $withRoute): array {
        if(!$request->user()->isDriver()&&($simulation=\App\Services\TripTest::passengerStatus($ride,$request->user()))){
            $simulation['navigation']['carbon']=LiveCarbon::marker($ride);
            return $simulation;
        }
        $navigation=TripNavigation::status($ride,$request->user());
        $navigation['carbon']=LiveCarbon::marker($ride);
        if(!$navigation['isDriver']){
            $start=collect($navigation['stops'])->firstWhere('kind','pickup')??$navigation['stops'][0]??null;
            $destination=collect($navigation['stops'])->firstWhere('kind','dropoff');
            if($start&&$destination){
                $start['kind']='start';$start['done']=true;
                $navigation['stops']=[$start,$destination];$navigation['next']=$destination;
                $navigation['allDroppedOff']=$destination['done'];
            }
            $navigation['passengerJourney']=true;
        }
        $payload=['navigation'=>$navigation];
        if($withRoute)$payload['route']=NavigationRoad::forNavigation($navigation);
        return $payload;
    }
}
