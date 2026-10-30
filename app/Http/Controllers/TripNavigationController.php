<?php
namespace App\Http\Controllers;
use App\Models\Ride;
use App\Services\{TripNavigation,NavigationRoad};
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
        if($ride->ride_status!=='in_progress')return redirect($this->returnUrl($request))->header('Cache-Control','no-store, private');
        $navigation=TripNavigation::status($ride,$request->user());
        abort_unless(count($navigation['stops'])>1,422,'This journey has no usable route coordinates.');
        $road=NavigationRoad::route($navigation['stops']);
        return response()->view('trip-navigation',compact('ride','navigation','road'))->header('Cache-Control','no-store, private');
    }
    public function status(Request $request,int $ride){
        abort_unless(TripNavigation::ready(),503);$owned=$this->owned($request,$ride);
        if($owned->ride_status!=='in_progress')return response()->json(['ended'=>true,'redirectUrl'=>$this->returnUrl($request)])->header('Cache-Control','no-store, private');
        return response()->json(['navigation'=>TripNavigation::status($owned,$request->user())])->header('Cache-Control','no-store, private');
    }
    public function advance(Request $request,int $ride){
        $owned=$this->owned($request,$ride);abort_unless((int)$owned->driver_id===(int)$request->user()->user_id,403);
        $data=$request->validate(['stop_key'=>'required|string|max:180']);
        TripNavigation::advance((int)$request->user()->user_id,$ride,$data['stop_key']);return $this->status($request,$ride);
    }
    private function returnUrl(Request $request): string {return url($request->user()->isDriver()?'driver/schedule':'passenger/bookings');}
}
