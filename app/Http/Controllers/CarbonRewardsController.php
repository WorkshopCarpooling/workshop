<?php
namespace App\Http\Controllers;

use App\Models\Ride;
use App\Services\{CarbonRewards,CarbonEstimate,LocationLabel,Journey,RoutePlanner};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Crypt};

class CarbonRewardsController extends Controller
{
    private function user() { $user=Auth::user();abort_unless($user&&$user->isActive()&&($user->isAdmin()||$user->isPassenger()||$user->isDriver()),403);return $user; }
    private function back(string $notice) { return redirect('carbon/rewards')->with('carbonNotice',$notice); }
    public function index() {
        $user=$this->user();$ready=CarbonRewards::ready();$wallet=CarbonRewards::wallet((int)$user->user_id);
        $leaders=$ready?CarbonRewards::leaderboard():collect();$myRank=$leaders->search(fn($row)=>(int)$row->user_id===(int)$user->user_id);$myRank=$myRank===false?null:$myRank+1;
        $catalog=$ready?DB::table('carbon_reward_catalog as r')->select('r.*')->selectSub(function($q){$q->from('carbon_reward_vouchers as v')->selectRaw('COUNT(*)')->whereColumn('v.reward_id','r.id')->whereNull('v.claimed_at')->where('v.expires_at','>',now());},'stock')->when(!$user->isAdmin(),fn($q)=>$q->where('r.active',true))->orderBy('r.points_cost')->orderBy('r.id')->get():collect();
        $trips=collect();$declarations=collect();$settled=collect();$reports=collect();$ledger=collect();$receipts=collect();$allReceipts=collect();
        if($ready&&!$user->isAdmin()){
            $trips=Ride::whereIn('ride_status',['scheduled','in_progress','completed'])->where(fn($q)=>$q->where('driver_id',$user->user_id)->orWhereHas('bookings',fn($b)=>$b->where('passenger_id',$user->user_id)->where('booking_status','accepted')))
                ->whereHas('bookings',fn($b)=>$b->where('booking_status','accepted'))->with(['bookings','vehicle'])->orderByDesc('ride_id')->limit(30)->get();
            $declarations=DB::table('carbon_reward_declarations')->where('user_id',$user->user_id)->whereIn('ride_id',$trips->pluck('ride_id'))->get()->keyBy('ride_id');
            $settled=DB::table('carbon_reward_trips')->whereIn('ride_id',$trips->pluck('ride_id'))->get()->keyBy('ride_id');
            $reports=CarbonEstimate::ready()?DB::table('ride_carbon_reports')->whereIn('ride_id',$trips->pluck('ride_id'))->get()->keyBy('ride_id'):collect();
            $ledger=DB::table('carbon_reward_ledger')->where('user_id',$user->user_id)->orderByDesc('id')->limit(30)->get();
            $receipts=DB::table('carbon_reward_redemptions')->where('user_id',$user->user_id)->orderByDesc('id')->limit(30)->get();
        }
        if($ready&&$user->isAdmin())$allReceipts=DB::table('carbon_reward_redemptions')->orderByDesc('id')->limit(30)->get();
        return response()->view('carbon-rewards',compact('user','ready','wallet','leaders','myRank','catalog','trips','declarations','settled','reports','ledger','receipts','allReceipts'))->header('Cache-Control','no-store, private');
    }
    public function declare(Request $request,int $ride) {
        CarbonRewards::declare($this->user(),$ride,$request->all());
        return $this->back('Travel alternative saved. Eligible passenger points are added once the trip is complete, its footprint is saved and everyone has confirmed their alternative travel.');
    }
    public function redeem(Request $request,int $reward) {
        $data=$request->validate(['request_key'=>'required|uuid','confirm'=>'required|accepted','points_cost'=>'required|integer|between:1,1000000','discount_rm'=>['present','nullable','numeric','between:0.01,999999.99','regex:/^\d+(\.\d{1,2})?$/']]);
        $id=CarbonRewards::redeem($this->user(),$reward,$data['request_key'],$data);
        return redirect('carbon/rewards/receipts/'.$id)->with('carbonNotice','Reward claimed. Your voucher is reserved and points were deducted once.');
    }
    public function receipt(int $receipt) {
        $user=$this->user();abort_unless($user->isPassenger(),403);
        $record=DB::table('carbon_reward_redemptions')->where('id',$receipt)->where('user_id',$user->user_id)->first();abort_unless($record,404);
        $voucher=DB::table('carbon_reward_vouchers')->where('id',$record->voucher_id)->first();abort_unless($voucher,404);
        $code=Crypt::decryptString($voucher->encrypted_code);
        return response()->view('carbon-reward-receipt',compact('user','record','voucher','code'))->header('Cache-Control','no-store, private');
    }
    public function create(Request $request) {
        $user=$this->user();abort_unless($user->isAdmin()&&CarbonRewards::ready(),403);
        $data=$request->validate(['title'=>'required|string|max:120','provider'=>'required|string|max:80','terms'=>'required|string|max:2000']+$this->settingsRules());
        DB::table('carbon_reward_catalog')->insert($data+['active'=>false,'created_by'=>$user->user_id,'created_at'=>now(),'updated_at'=>now()]);
        return $this->back('Reward created as inactive. Add genuine voucher codes, then activate it.');
    }
    private function settingsRules(): array {
        return ['discount_rm'=>['required','numeric','between:0.01,999999.99','regex:/^\d+(\.\d{1,2})?$/'],'points_cost'=>'required|integer|between:1,1000000'];
    }
    public function settings(Request $request,int $reward) {
        $user=$this->user();abort_unless($user->isAdmin()&&CarbonRewards::ready(),403);
        $data=$request->validate($this->settingsRules());
        DB::transaction(function()use($reward,$data){
            abort_unless(DB::table('carbon_reward_catalog')->where('id',$reward)->lockForUpdate()->first(),404);
            DB::table('carbon_reward_catalog')->where('id',$reward)->update($data+['updated_at'=>now()]);
        },3);
        return $this->back('Voucher discount and points required updated. Previous claims keep their original discount and points cost.');
    }
    public function vouchers(Request $request,int $reward) {
        $user=$this->user();abort_unless($user->isAdmin()&&CarbonRewards::ready(),403);
        $data=$request->validate(['codes'=>'required|string|max:20000','expires_at'=>'required|date|after:now']);
        $codes=array_values(array_filter(array_map('trim',preg_split('/\R/',$data['codes']))));
        if(!$codes||count($codes)>100||count($codes)!==count(array_unique($codes))||collect($codes)->contains(fn($code)=>mb_strlen($code)>200))throw \Illuminate\Validation\ValidationException::withMessages(['codes'=>'Enter up to 100 distinct voucher codes, one per line, each at most 200 characters.']);
        CarbonRewards::addVouchers($user,$reward,$codes,\Carbon\Carbon::parse($data['expires_at'])->format('Y-m-d H:i:s'));
        return $this->back(count($codes).' voucher codes added securely. Codes become visible only to the passenger who redeems each voucher.');
    }
    public function availability(Request $request,int $reward) {
        $user=$this->user();abort_unless($user->isAdmin()&&CarbonRewards::ready(),403);$data=$request->validate(['active'=>'required|boolean']);
        abort_unless(DB::table('carbon_reward_catalog')->where('id',$reward)->first(),404);
        DB::table('carbon_reward_catalog')->where('id',$reward)->update(['active'=>$data['active'],'updated_at'=>now()]);
        return $this->back('Reward availability updated. Previous claims keep their original points cost and voucher.');
    }
}
