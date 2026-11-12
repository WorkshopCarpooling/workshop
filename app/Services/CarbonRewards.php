<?php
namespace App\Services;

use App\Models\{Ride,User};
use Illuminate\Support\Facades\{DB,Schema,Crypt};
use Illuminate\Validation\ValidationException;

final class CarbonRewards
{
    public static function ready(): bool {
        foreach(['carbon_reward_ledger','carbon_reward_trips','carbon_reward_declarations','carbon_reward_catalog','carbon_reward_vouchers','carbon_reward_redemptions'] as $table)if(!Schema::hasTable($table))return false;
        return Schema::hasColumn('carbon_reward_catalog','discount_rm')&&Schema::hasColumn('carbon_reward_redemptions','discount_rm');
    }
    private static function reject(string $message): void { throw ValidationException::withMessages(['rewards'=>$message]); }
    public static function participants(Ride $ride): array {
        $ride->loadMissing('bookings');$people=[(int)$ride->driver_id=>1];
        foreach($ride->bookings->where('booking_status','accepted') as $b)$people[(int)$b->passenger_id]=($people[(int)$b->passenger_id]??0)+(int)$b->seats_requested;
        return $people;
    }
    public static function owned(User $user,int $id): Ride {
        abort_unless($user->isActive()&&($user->isPassenger()||$user->isDriver()),403);
        return Ride::whereKey($id)->where(function($q)use($user){$q->where('driver_id',$user->user_id)->orWhereHas('bookings',fn($b)=>$b->where('passenger_id',$user->user_id)->where('booking_status','accepted'));})->with(['bookings','vehicle'])->firstOrFail();
    }
    public static function declare(User $user,int $id,array $input): void {
        abort_unless(self::ready(),503);
        $ride=self::owned($user,$id);abort_unless(in_array($ride->ride_status,['scheduled','in_progress','completed'],true),409);
        $people=self::participants($ride);$maxCars=(int)$people[(int)$user->user_id];
        $data=validator($input,[
            'alternative'=>'required|in:separate_car,no_separate_car','confirmed'=>'required|accepted',
            'cars'=>'required_if:alternative,separate_car|nullable|integer|between:1,'.$maxCars,
            'distance_km'=>'required_if:alternative,separate_car|nullable|numeric|between:0.001,500',
            'fuel'=>'required_if:alternative,separate_car|nullable|in:'.implode(',',array_keys(config('carbon.fuels'))),
            'consumption'=>'required_if:alternative,separate_car|nullable|numeric|between:0.1,50',
        ])->validate();
        $separate=$data['alternative']==='separate_car';
        $values=['alternative'=>$data['alternative'],'cars'=>$separate?(int)$data['cars']:0,'distance_km'=>$separate?$data['distance_km']:null,
            'fuel'=>$separate?$data['fuel']:null,'consumption'=>$separate?$data['consumption']:null,
            'fuel_factor'=>$separate?config('carbon.fuels.'.$data['fuel'].'.factor'):null,
            'baseline_kg'=>$separate?round(CarbonEstimate::emissions((float)$data['distance_km'],(float)$data['consumption'],$data['fuel'])*(int)$data['cars'],6):0];
        DB::transaction(function()use($id,$user,$values){
            $locked=Ride::whereKey($id)->lockForUpdate()->firstOrFail();
            if(!in_array($locked->ride_status,['scheduled','in_progress','completed'],true)||!isset(self::participants($locked)[(int)$user->user_id]))abort(409);
            if(DB::table('carbon_reward_trips')->where('ride_id',$id)->exists())self::reject('This trip’s rewards are already finalised. Its original inputs are preserved.');
            DB::table('carbon_reward_declarations')->updateOrInsert(['ride_id'=>$id,'user_id'=>$user->user_id],$values+['created_at'=>now(),'updated_at'=>now()]);
        },3);
        self::finalize($id);
    }
    public static function compare(Ride $ride,?float $sharedKg): array {
        $missing=['status'=>'unavailable','level'=>'unknown','remaining_percent'=>null,'baseline_kg'=>null,'shared_kg'=>$sharedKg,'saved_kg'=>null,
            'reason'=>'Driver and accepted passengers must confirm their alternative travel in Carbon Rewards.'];
        if(!self::ready()||$sharedKg===null||!is_finite($sharedKg)||$sharedKg<0)return $missing;
        $people=self::participants($ride);$declarations=DB::table('carbon_reward_declarations')->where('ride_id',$ride->ride_id)->get()->keyBy('user_id');
        if(count($people)<2)return array_replace($missing,['reason'=>'A shared ride needs at least one accepted passenger.']);
        $baseline=0;
        foreach($people as $id=>$seats){
            $d=$declarations->get($id);if(!$d||$d->cars>$seats)return $missing;
            $baseline+=(float)$d->baseline_kg;
        }
        if($baseline<=0)return array_replace($missing,['reason'=>'No separate-car emissions were declared. A saving percentage cannot be calculated.']);
        $percent=$sharedKg/$baseline*100;
        return ['status'=>'estimated','level'=>$percent<=65?'low':($percent<=80?'medium':'high'),
            'remaining_percent'=>round($percent,4),'baseline_kg'=>round($baseline,6),'shared_kg'=>round($sharedKg,6),
            'saved_kg'=>round($baseline-$sharedKg,6),'reason'=>null];
    }
    public static function finalize(int $id): void {
        if(!self::ready()||!CarbonEstimate::ready())return;
        DB::transaction(function()use($id){
            $ride=Ride::whereKey($id)->lockForUpdate()->with('bookings')->first();
            if(!$ride||$ride->ride_status!=='completed'||!$ride->completed_at||DB::table('carbon_reward_trips')->where('ride_id',$id)->exists())return;
            // Rewards require a saved completed-trip report, never a read-only preview.
            $report=DB::table('ride_carbon_reports')->where('ride_id',$id)->first();if(!$report)return;
            $comparison=self::compare($ride,(float)$report->shared_kg);if($comparison['status']!=='estimated')return;
            $people=self::participants($ride);$occupants=array_sum($people);$rate=(int)config('carbon_rewards.points_per_kg',100);
            $declarations=DB::table('carbon_reward_declarations')->where('ride_id',$id)->get();
            DB::table('carbon_reward_trips')->insert([
                'ride_id'=>$id,'vehicle_id'=>$ride->vehicle_id,'completed_at'=>$ride->completed_at,'baseline_kg'=>$comparison['baseline_kg'],
                'shared_kg'=>$comparison['shared_kg'],'saved_kg'=>$comparison['saved_kg'],'remaining_percent'=>$comparison['remaining_percent'],
                'points_per_kg'=>$rate,'method'=>config('carbon_rewards.method','declared-separate-car-comparison-v1'),
                'details'=>json_encode(['declarations'=>$declarations,'occupants'=>$occupants,'report_id'=>$report->id,'report_details'=>$report->details,
                    'allocation'=>'positive trip saving divided by driver + accepted passenger seats; driver share receives no points'],JSON_THROW_ON_ERROR),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            ksort($people);
            foreach($people as $userId=>$seats){
                if($userId===(int)$ride->driver_id)continue;
                $person=User::whereKey($userId)->lockForUpdate()->first();if(!$person||!$person->isPassenger())continue;
                $share=max(0,$comparison['saved_kg'])*$seats/$occupants;
                DB::table('carbon_reward_ledger')->insert(['user_id'=>$userId,'ride_id'=>$id,'kind'=>'earn',
                    'points'=>(int)floor($share*$rate+0.0000001),'saved_kg'=>round($share,6),'created_at'=>now(),'updated_at'=>now()]);
            }
        },3);
    }
    public static function daily(Ride $ride,?float $activeKg): array {
        $current=self::compare($ride,$activeKg);
        if($current['status']!=='estimated')return $current+['basis'=>'daily_completed_and_projected_active','date'=>now()->format('Y-m-d')];
        $start=now()->startOfDay();
        $rows=DB::table('carbon_reward_trips')->where('vehicle_id',$ride->vehicle_id)->where('ride_id','<>',$ride->ride_id)
            ->where('completed_at','>=',$start)->where('completed_at','<',$start->copy()->addDay())->get();
        $baseline=(float)$rows->sum('baseline_kg')+$current['baseline_kg'];$shared=(float)$rows->sum('shared_kg')+$activeKg;
        $percent=$shared/$baseline*100;
        return ['status'=>'estimated','level'=>$percent<=65?'low':($percent<=80?'medium':'high'),'remaining_percent'=>round($percent,4),
            'baseline_kg'=>round($baseline,6),'shared_kg'=>round($shared,6),'saved_kg'=>round($baseline-$shared,6),
            'basis'=>'daily_completed_and_projected_active','date'=>now()->format('Y-m-d'),'reason'=>null];
    }
    public static function wallet(int $userId): array {
        if(!self::ready())return ['balance'=>0,'earned'=>0,'saved_kg'=>0];
        $q=DB::table('carbon_reward_ledger')->where('user_id',$userId);
        return ['balance'=>(int)(clone $q)->sum('points'),'earned'=>(int)(clone $q)->where('kind','earn')->sum('points'),
            'saved_kg'=>(float)(clone $q)->where('kind','earn')->sum('saved_kg')];
    }
    public static function leaderboard() {
        return DB::table('carbon_reward_ledger as l')->join('users as u','u.user_id','=','l.user_id')
            ->where('l.kind','earn')->where('u.role','student')->where('u.account_type','passenger')->where('u.account_status','active')
            ->groupBy('u.user_id','u.full_name')->select('u.user_id','u.full_name')->selectRaw('SUM(l.points) as earned, SUM(l.saved_kg) as saved_kg')
            ->havingRaw('SUM(l.points) > 0')->orderByDesc('earned')->orderByDesc('saved_kg')->orderBy('u.user_id')->get();
    }
    public static function redeem(User $user,int $rewardId,string $requestKey,?array $reviewed=null): int {
        abort_unless(self::ready()&&$user->isActive()&&$user->isPassenger(),403);
        return DB::transaction(function()use($user,$rewardId,$requestKey,$reviewed){
            $lockedUser=User::whereKey($user->user_id)->lockForUpdate()->firstOrFail();abort_unless($lockedUser->isActive()&&$lockedUser->isPassenger(),403);
            $old=DB::table('carbon_reward_redemptions')->where('request_key',$requestKey)->first();
            if($old){abort_unless((int)$old->user_id===(int)$user->user_id&&(int)$old->reward_id===$rewardId,409);return (int)$old->id;}
            $reward=DB::table('carbon_reward_catalog')->where('id',$rewardId)->lockForUpdate()->first();
            if(!$reward||!$reward->active)self::reject('This reward is unavailable. Your points have not been spent.');
            if($reviewed!==null&&((int)$reviewed['points_cost']!==(int)$reward->points_cost||
                ($reviewed['discount_rm']===null?null:number_format((float)$reviewed['discount_rm'],2,'.',''))!==
                ($reward->discount_rm===null?null:number_format((float)$reward->discount_rm,2,'.','')))) {
                self::reject('This voucher’s discount or points cost has changed. Refresh Rewards and review it again. Your points have not been spent.');
            }
            if(self::wallet((int)$user->user_id)['balance']<(int)$reward->points_cost)self::reject('You do not have enough points for this reward.');
            $voucher=DB::table('carbon_reward_vouchers')->where('reward_id',$rewardId)->whereNull('claimed_at')->where('expires_at','>',now())->orderBy('expires_at')->orderBy('id')->lockForUpdate()->first();
            if(!$voucher)self::reject('This reward is out of stock. Your points have not been spent.');
            $id=DB::table('carbon_reward_redemptions')->insertGetId(['user_id'=>$user->user_id,'reward_id'=>$rewardId,'voucher_id'=>$voucher->id,
                'request_key'=>$requestKey,'points_cost'=>$reward->points_cost,'discount_rm'=>$reward->discount_rm,'title'=>$reward->title,'provider'=>$reward->provider,'terms'=>$reward->terms,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('carbon_reward_vouchers')->where('id',$voucher->id)->update(['claimed_at'=>now(),'updated_at'=>now()]);
            DB::table('carbon_reward_ledger')->insert(['user_id'=>$user->user_id,'redemption_id'=>$id,'kind'=>'redeem','points'=>-(int)$reward->points_cost,'saved_kg'=>0,'created_at'=>now(),'updated_at'=>now()]);
            return $id;
        },3);
    }
    public static function addVouchers(User $admin,int $id,array $codes,string $expires): void {
        abort_unless($admin->isAdmin()&&$admin->isActive(),403);
        DB::transaction(function()use($admin,$id,$codes,$expires){
            abort_unless(DB::table('carbon_reward_catalog')->where('id',$id)->lockForUpdate()->first(),404);
            foreach($codes as $code){$hash=hash('sha256',$code);
                if(DB::table('carbon_reward_vouchers')->where('code_hash',$hash)->exists())self::reject('A voucher code in this batch already exists. No codes were added.');
                DB::table('carbon_reward_vouchers')->insert(['reward_id'=>$id,'encrypted_code'=>Crypt::encryptString($code),'code_hash'=>$hash,
                    'expires_at'=>$expires,'created_at'=>now(),'updated_at'=>now()]);
            }
        },3);
    }
}
