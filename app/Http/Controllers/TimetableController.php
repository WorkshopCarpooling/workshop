<?php

namespace App\Http\Controllers;

use App\Models\{RideRequest, TimetableRidePlan};
use App\Services\{Journey, RideAppointments, TimetableCommutes, TimetableRideQuote};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Illuminate\Validation\ValidationException;

class TimetableController extends Controller
{
    private function session(int $id): object
    {
        abort_unless(Schema::hasTable('commute_timetable'),503);
        $session=DB::table('commute_timetable')->where('id',$id)->where('user_id',Auth::id())->first();
        abort_unless($session,404);
        return $session;
    }

    private function inputs(Request $request): array
    {
        $journey=Journey::fields($request);
        $data=$request->validate([
            'seats_requested'=>'required|integer|between:1,3',
            'arrival_buffer_minutes'=>'required|integer|between:0,120',
            'booking_mode'=>'required|in:once,weekly',
            'starts_on'=>'required_if:booking_mode,weekly|nullable|date_format:Y-m-d',
            'ends_on'=>'required_if:booking_mode,weekly|nullable|date_format:Y-m-d|after_or_equal:starts_on|after_or_equal:today',
        ]);
        foreach(['pickup_latitude','pickup_longitude','dropoff_latitude','dropoff_longitude'] as $key) $journey[$key]=(float)$journey[$key];
        return $journey+['seats_requested'=>(int)$data['seats_requested'],'arrival_buffer_minutes'=>(int)$data['arrival_buffer_minutes'],
            'booking_mode'=>$data['booking_mode'],'starts_on'=>$data['booking_mode']==='weekly'?$data['starts_on']:now()->format('Y-m-d'),
            'ends_on'=>$data['booking_mode']==='weekly'?$data['ends_on']:now()->addWeek()->format('Y-m-d')];
    }

    public function index()
    {
        if(Auth::user()->isAdmin()) return redirect('admin.php');
        if(!Auth::user()->isPassenger()) return redirect('driver.php');
        $ready=Schema::hasTable('commute_timetable');
        if(TimetableCommutes::ready()) TimetableCommutes::maintain();
        $sessions=$ready?DB::table('commute_timetable')->where('user_id',Auth::id())->orderBy('weekday')->orderBy('starts_at')->get():collect();
        $plans=TimetableCommutes::ready()?TimetableRidePlan::where('user_id',Auth::id())->whereNotNull('timetable_id')->get()->keyBy('timetable_id'):collect();
        $rides=TimetableCommutes::ready()?RideRequest::where('passenger_id',Auth::id())->whereNotNull('timetable_entry_id')
            ->where('scheduled_for','>',now())->whereIn('request_status',['scheduled','pending','accepted'])->orderBy('scheduled_for')->get()->groupBy('timetable_entry_id'):collect();
        return response()->view('timetable',['sessions'=>$sessions,'plans'=>$plans,'nextRides'=>$rides,'ready'=>$ready,'commuteReady'=>TimetableCommutes::ready()])
            ->header('Cache-Control','no-store, private');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isPassenger() && !Auth::user()->isAdmin() && Auth::user()->isActive(),403);
        abort_unless(Schema::hasTable('commute_timetable'),503);
        $data=$request->validate([
            'title'=>'required|string|max:100','weekday'=>'required|integer|between:0,6',
            'starts_at'=>'required|date_format:H:i','ends_at'=>'required|date_format:H:i|after:starts_at',
            'location'=>'required|string|max:180',
        ]);
        $overlap=DB::table('commute_timetable')->where('user_id',Auth::id())->where('weekday',$data['weekday'])
            ->where('starts_at','<',$data['ends_at'])->where('ends_at','>',$data['starts_at'])->exists();
        if($overlap) throw ValidationException::withMessages(['timetable'=>'This session overlaps with another timetable entry.']);
        DB::table('commute_timetable')->insert($data+['user_id'=>Auth::id(),'created_at'=>now(),'updated_at'=>now()]);
        return redirect('timetable')->with('commuteNotice','Your session has been added. You can now plan its ride.');
    }

    public function destroy(int $id)
    {
        abort_unless(Auth::user()->isPassenger() && !Auth::user()->isAdmin() && Auth::user()->isActive(),403);
        $this->session($id);
        TimetableCommutes::removeSession((int)Auth::id(),$id);
        return redirect('timetable')->with('commuteNotice','Session removed. Its future unconfirmed timetable rides have been cancelled.');
    }

    public function ride(Request $request,int $id)
    {
        $session=$this->session($id);
        abort_unless(TimetableCommutes::ready(),503,'Timetable ride planning is temporarily unavailable.');
        if($request->expectsJson()) {
            $inputs=$this->inputs($request);
            $trip=(new RoutesController)->computeRoute(Request::create('/api/routes/compute','POST',[
                'origin_lat'=>$inputs['pickup_latitude'],'origin_lng'=>$inputs['pickup_longitude'],'origin_name'=>$inputs['pickup_label'],
                'dest_lat'=>$inputs['dropoff_latitude'],'dest_lng'=>$inputs['dropoff_longitude'],'dest_name'=>$inputs['dropoff_label'],
            ]))->getData(true);
            $minutes=max(1,(int)ceil($trip['durationSeconds']/60));
            $timing=TimetableCommutes::nextSession($session,$inputs['starts_on'],$inputs['ends_on'],$minutes,$inputs['arrival_buffer_minutes']);
            if(!$timing) throw ValidationException::withMessages(['ends_on'=>'There is no upcoming session with enough time for this journey in the selected dates. Choose a later date range.']);
            return response()->json(['fare'=>TimetableRideQuote::issue((int)Auth::id(),$session,$inputs,$trip,$timing),
                'timing'=>$timing,'sessionTitle'=>$session->title,'bookingMode'=>$inputs['booking_mode']])
                ->header('Cache-Control','no-store, private');
        }
        $plan=TimetableRidePlan::where('user_id',Auth::id())->where('timetable_id',$id)->first();
        $pickerValues=$plan?$plan->only(['pickup_label','pickup_latitude','pickup_longitude','dropoff_label','dropoff_latitude','dropoff_longitude']):['dropoff_label'=>$session->location];
        return response()->view('timetable-ride',compact('session','plan','pickerValues'))->header('Cache-Control','no-store, private');
    }

    public function confirm(Request $request,int $id)
    {
        $this->session($id);
        abort_unless(TimetableCommutes::ready(),503);
        $inputs=$this->inputs($request);
        $data=$request->validate(['fare_quote'=>'required|string|max:8192']);
        if($inputs['booking_mode']==='weekly') $request->validate(['weekly_consent'=>'accepted']);
        $appointment=TimetableCommutes::confirm((int)Auth::id(),$id,$inputs,$data['fare_quote']);
        $request->session()->flash('commuteNotice',$inputs['booking_mode']==='weekly'?'Weekly rides enabled. Your next appointment is in Schedule.':'Your next-session ride is saved in Schedule.');
        if($request->expectsJson()) return response()->json(['appointment'=>RideAppointments::payload($appointment),'redirectUrl'=>url('timetable')])
            ->header('Cache-Control','no-store, private');
        return redirect('timetable')->with('commuteNotice','Your timetable ride is saved in Schedule.');
    }

    public function toggle(Request $request,int $id)
    {
        $this->session($id);abort_unless(TimetableCommutes::ready(),503);
        $data=$request->validate(['enabled'=>'required|boolean']);
        TimetableCommutes::toggle((int)Auth::id(),$id,(bool)$data['enabled']);
        return redirect('timetable')->with('commuteNotice',$data['enabled']?'Weekly rides resumed.':'Future weekly rides paused.');
    }
}
