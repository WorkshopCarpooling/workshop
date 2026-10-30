<?php
namespace App\Http\Controllers;

use App\Services\{CarbonEstimate,CarbonImpact,CarbonPreferences};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Schema};
use Illuminate\Validation\ValidationException;

class CarbonToolsController extends Controller
{
    public function preferences(Request $request)
    {
        $user=Auth::user();abort_unless($user->isActive()&&($user->isPassenger()||$user->isDriver()),403);
        abort_unless(CarbonPreferences::ready(),503);
        $request->validate(['remember_consent'=>'accepted']);
        if($user->isPassenger()) {
            $request->validate(['baseline_mode'=>'required|in:'.implode(',',array_keys(config('carbon.modes')))]);
            $data=CarbonEstimate::baseline($request,3);unset($data['baseline_distance_km']);
        } else $data=$request->validate(['driver_would_travel'=>'required|in:yes,no,unknown']);
        DB::transaction(function()use($data,$user){
            \App\Models\User::whereKey($user->user_id)->lockForUpdate()->firstOrFail();
            $existing=DB::table('carbon_travel_preferences')->where('user_id',$user->user_id)->first();
            $values=$data+['updated_at'=>now()];
            if($existing)DB::table('carbon_travel_preferences')->where('user_id',$user->user_id)->update($values);
            else DB::table('carbon_travel_preferences')->insert($values+['user_id'=>$user->user_id,'created_at'=>now()]);
        });
        return redirect('carbon')->with('carbonNotice','Usual travel saved. Future bookings and completed trips can reuse it; existing trip answers stay unchanged.');
    }
    public function prices(Request $request)
    {
        abort_unless(Auth::user()->isAdmin()&&Auth::user()->isActive(),403);
        abort_unless(Schema::hasTable('carbon_fuel_prices'),503);
        $data=$request->validate(['month'=>'required|date_format:Y-m','prices'=>'required|array|min:1','prices.*'=>'nullable|numeric|between:0.01,1000','reference'=>'required|string|max:180']);
        if(array_diff(array_keys($data['prices']),array_keys(config('carbon.fuels'))))throw ValidationException::withMessages(['prices'=>'Choose a supported fuel.']);
        DB::transaction(function()use($data){foreach($data['prices'] as $fuel=>$price)DB::table('carbon_fuel_prices')->insert([
            'month'=>$data['month'].'-01','fuel'=>$fuel,'price_per_litre'=>$price===''?null:$price,'reference'=>trim($data['reference']),
            'recorded_by'=>Auth::id(),'created_at'=>now(),'updated_at'=>now()]);});
        return redirect('carbon?month='.$data['month'])->with('carbonNotice','Monthly fuel prices saved. Cost estimates use these assumptions; previous price revisions are retained.');
    }
    public function export(Request $request)
    {
        abort_unless(Auth::user()->isAdmin()&&Auth::user()->isActive(),403);
        $month=CarbonImpact::month($request->query('month'));$impact=CarbonImpact::monthly(Auth::user(),$month);
        return response()->streamDownload(function()use($impact){
            $out=fopen('php://output','w');
            fputcsv($out,['month','ride_id','completed_at','accepted_passenger_seats','booked_occupancy','distance_basis','shared_km','shared_kg_co2e','baseline_kg_co2e','net_avoided_kg_co2e','shared_fuel_litres','baseline_fuel_litres','net_fuel_saved_litres','net_fuel_cost_saved_rm','price_inputs_json','comparison_status','method','source_url']);
            foreach($impact['rows'] as $row) {
                $ride=$row['ride'];$report=$row['report'];$metrics=$row['metrics'];$details=$report?json_decode($report->details,true):[];
                fputcsv($out,[$impact['month'],$ride->ride_id,$ride->completed_at->toIso8601String(),$row['accepted_seats'],1+$row['accepted_seats'],
                    $report->distance_basis??'unavailable',$report->shared_km??null,$report->shared_kg??null,
                    ($metrics['comparison_complete']??false)?$report->baseline_kg:null,($metrics['comparison_complete']??false)?$report->avoided_kg:null,
                    $metrics['shared_litres']??null,$metrics['baseline_litres']??null,$metrics['saved_litres']??null,$metrics['saved_rm']??null,
                    json_encode($metrics['price_inputs']??[],JSON_UNESCAPED_SLASHES),!$report?'no_report':($report->needs_review?'needs_review':(($metrics['comparison_complete']??false)?'complete':'incomplete')),
                    $report->method??'',$details['source_url']??'']);
            }
            fclose($out);
        },'rideutem-monthly-impact-'.$month.'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store, private']);
    }
}
