<?php
namespace App\Services;

use App\Models\{Ride,User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB,Schema};

final class CarbonImpact
{
    public static function month(?string $month): string
    {
        return validator(['month'=>$month??now()->format('Y-m')],['month'=>'required|date_format:Y-m'])->validate()['month'];
    }
    public static function prices(string $month): array
    {
        if(!Schema::hasTable('carbon_fuel_prices')) return [];
        return DB::table('carbon_fuel_prices')->where('month',$month.'-01')->orderByDesc('id')->get()->unique('fuel')->keyBy('fuel')->all();
    }
    public static function metrics(object $report,array $prices): array
    {
        $details=json_decode($report->details,true)?:[];
        $fuel=$details['fuel']??'';$consumption=(float)($details['litres_per_100km']??0);
        $shared=(float)$report->shared_km*$consumption/100;
        $valid=is_finite($shared)&&$shared>0&&$consumption>0&&$consumption<=50;
        $complete=$valid&&!$report->needs_review&&$report->baseline_kg!==null&&$report->avoided_kg!==null&&($details['baseline_complete']??false);
        $baseline=[];
        $add=function(string $kind,float $litres)use(&$baseline){$baseline[$kind]=($baseline[$kind]??0)+$litres;};
        if(($details['driver_would_travel']??'unknown')==='yes') $add($fuel,(float)$report->driver_baseline_km*$consumption/100);
        elseif(($details['driver_would_travel']??'unknown')!=='no') $complete=false;
        foreach($details['bookings']??[] as $line) {
            if(in_array($line['mode'],['walk_cycle','no_trip'],true)) continue;
            if($line['mode']!=='drive_alone'||($line['km']??0)<=0||($line['cars']??0)<1||($line['litres_per_100km']??0)<=0||empty($line['fuel'])) {$complete=false;continue;}
            $add($line['fuel'],(float)$line['km']*(float)$line['litres_per_100km']/100*(int)$line['cars']);
        }
        $usedPrices=[];$baselineCost=0;$sharedCost=null;$costComplete=$complete;
        foreach(array_unique(array_merge([$fuel],array_keys($baseline))) as $kind) {
            $price=$prices[$kind]??null;
            if(!$price || $price->price_per_litre===null) {$costComplete=false;continue;}
            $unit=(float)$price->price_per_litre;
            $usedPrices[$kind]=['id'=>$price->id,'rm_per_litre'=>$unit,'reference'=>$price->reference,'recorded_at'=>$price->created_at];
            $baselineCost+=($baseline[$kind]??0)*$unit;
            if($kind===$fuel)$sharedCost=$shared*$unit;
        }
        return ['shared_litres'=>$valid?$shared:null,'baseline_litres'=>$complete?array_sum($baseline):null,
            'saved_litres'=>$complete?array_sum($baseline)-$shared:null,'baseline_by_fuel'=>$complete?$baseline:[],
            'shared_fuel'=>$fuel,'comparison_complete'=>$complete,'baseline_cost'=>$costComplete?$baselineCost:null,
            'shared_cost'=>$sharedCost,'saved_rm'=>$costComplete?$baselineCost-$sharedCost:null,'price_inputs'=>$usedPrices];
    }
    public static function monthly(User $user,string $month): array
    {
        $start=CarbonImmutable::createFromFormat('!Y-m',$month,config('app.timezone'));
        $query=Ride::where('ride_status','completed')->where('completed_at','>=',$start)->where('completed_at','<',$start->addMonth())
            ->with(['bookings'=>fn($query)=>$query->where('booking_status','accepted')]);
        if($user->isDriver())$query->where('driver_id',$user->user_id);
        elseif(!$user->isAdmin())$query->whereHas('bookings',fn($q)=>$q->where('passenger_id',$user->user_id)->where('booking_status','accepted'));
        $rides=$query->orderBy('completed_at')->get();
        $reports=CarbonEstimate::ready()?DB::table('ride_carbon_reports')->whereIn('ride_id',$rides->pluck('ride_id'))->get()->keyBy('ride_id'):collect();
        $prices=self::prices($month);$rows=[];
        $totals=['trips'=>$rides->count(),'accepted_seats'=>0,'occupancy'=>null,'emission_trips'=>0,'shared_kg'=>0,'shared_litres'=>0,
            'comparison_trips'=>0,'avoided_kg'=>0,'saved_litres'=>0,'cost_trips'=>0,'saved_rm'=>0,'missing_reports'=>0,
            'quality'=>['odometer'=>0,'road_estimate'=>0,'planned_estimate'=>0,'unknown'=>0]];
        foreach($rides as $ride) {
            $seats=$ride->bookings->sum('seats_requested');$totals['accepted_seats']+=$seats;
            $report=$reports[$ride->ride_id]??null;$metrics=$report?self::metrics($report,$prices):null;
            if(!$report)$totals['missing_reports']++;
            else {
                $basis=array_key_exists($report->distance_basis,$totals['quality'])?$report->distance_basis:'unknown';$totals['quality'][$basis]++;
                $totals['emission_trips']++;$totals['shared_kg']+=(float)$report->shared_kg;
                $totals['shared_litres']+=$metrics['shared_litres']??0;
                if($metrics['comparison_complete']){$totals['comparison_trips']++;$totals['avoided_kg']+=(float)$report->avoided_kg;$totals['saved_litres']+=$metrics['saved_litres'];}
                if($metrics['saved_rm']!==null){$totals['cost_trips']++;$totals['saved_rm']+=$metrics['saved_rm'];}
            }
            $rows[]=['ride'=>$ride,'report'=>$report,'metrics'=>$metrics,'accepted_seats'=>$seats];
        }
        if($totals['trips'])$totals['occupancy']=($totals['trips']+$totals['accepted_seats'])/$totals['trips'];
        return ['month'=>$month,'label'=>$start->format('F Y'),'totals'=>$totals,'prices'=>$prices,'rows'=>$rows,
            'ready'=>CarbonPreferences::ready()&&Schema::hasTable('carbon_fuel_prices')];
    }
}
