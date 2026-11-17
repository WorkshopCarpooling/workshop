<?php
namespace App\Http\Controllers;
use App\Models\Ride;
use App\Services\{TripTest,LiveCarbon};
use Illuminate\Http\Request;

class TripTestController extends Controller {
    private function owned(Request $request,int $ride): Ride {
        abort_unless(TripTest::enabled(),404);
        $driver=$request->user();abort_unless($driver&&$driver->isDriver()&&$driver->isActive(),403);
        return Ride::whereKey($ride)->where('driver_id',$driver->user_id)->with('bookings')->firstOrFail();
    }
    public function show(Request $request,int $ride){
        $ride=$this->owned($request,$ride);
        if(in_array($ride->ride_status,['completed','cancelled'],true))return redirect('driver/requests')->header('Cache-Control','no-store, private');
        $payload=TripTest::start($ride,$request->user());
        $navigation=$payload['navigation'];$navigation['carbon']=LiveCarbon::marker($ride);$road=$payload['route'];
        return response()->view('trip-navigation',compact('ride','navigation','road'))->header('Cache-Control','no-store, private');
    }
    public function status(Request $request,int $ride){
        $owned=$this->owned($request,$ride);
        if(!in_array($owned->ride_status,['scheduled','in_progress'],true))$payload=['ended'=>true];
        else $payload=TripTest::status($owned,$request->user());
        if(isset($payload['navigation']))$payload['navigation']['carbon']=LiveCarbon::marker($owned);
        return response()->json($payload)->header('Cache-Control','no-store, private');
    }
    public function advance(Request $request,int $ride){
        $owned=$this->owned($request,$ride);$data=$request->validate(['stop_key'=>'required|string|max:180','test_run_id'=>'required|uuid']);
        $payload=TripTest::advance($owned,$request->user(),$data['stop_key'],$data['test_run_id']);
        $payload['navigation']['carbon']=LiveCarbon::marker($owned->load('bookings'));
        return response()->json($payload)->header('Cache-Control','no-store, private');
    }
    public function finish(Request $request,int $ride){
        $owned=$this->owned($request,$ride);$data=$request->validate(['test_run_id'=>'required|uuid']);
        TripTest::finish($owned,$request->user(),$data['test_run_id']);
        return redirect('driver/requests')->with('driver_notice','Trip completed. The order has moved to trip history for you and your passengers.')->with('driver_notice_type','success')->header('Cache-Control','no-store, private');
    }
}
