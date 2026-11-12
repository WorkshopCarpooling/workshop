<?php
namespace App\Services;

use App\Models\{User,Ride,RideBooking,RideRequest};
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;

final class MemberAccess
{
    public static function ready(): bool
    {
        return Schema::hasColumns('users',['removed_at','removed_by','status_before_removal','access_version']);
    }
    public static function remove(int $memberId,int $adminId): void
    {
        abort_unless(self::ready(),503,'Member removal is temporarily unavailable.');
        DB::transaction(function()use($memberId,$adminId){
            abort_unless(User::whereKey($adminId)->where('role','admin')->where('account_status','active')->exists(),403);
            $member=User::whereKey($memberId)->where('role','student')->lockForUpdate()->firstOrFail();
            if($member->removed_at)return;
            // Serialize pending instant acceptances before gathering their newly created rides.
            if(Schema::hasTable('ride_requests'))RideRequest::where(fn($q)=>$q->where('passenger_id',$memberId)->orWhere('driver_id',$memberId))
                ->whereIn('request_status',['scheduled','paused','pending','accepted'])->orderBy('request_id')->lockForUpdate()->get();
            $rides=Ride::where(fn($q)=>$q->where('driver_id',$memberId)->orWhereHas('bookings',fn($b)=>$b->where('passenger_id',$memberId)->whereIn('booking_status',['pending','accepted'])))
                ->whereIn('ride_status',['scheduled','in_progress'])->orderBy('ride_id')->lockForUpdate()->get();
            if($rides->contains(fn($ride)=>$ride->ride_status==='in_progress'&&((int)$ride->driver_id===$memberId||$ride->bookings()->where('passenger_id',$memberId)->where('booking_status','accepted')->exists()))) {
                throw ValidationException::withMessages(['member'=>'This member is in an ongoing trip. Finish that trip before removing their access.']);
            }
            $cancelled=[];
            foreach($rides->where('ride_status','scheduled') as $ride) {
                $wholeRide=(int)$ride->driver_id===$memberId;
                $bookings=RideBooking::where('ride_id',$ride->ride_id)->whereIn('booking_status',['pending','accepted'])->orderBy('booking_id')->lockForUpdate()->get();
                if(!$wholeRide&&in_array($ride->ride_mode,['now','appointment'],true)) {
                    $wholeRide=$bookings->where('booking_status','accepted')->where('passenger_id','!=',$memberId)->isEmpty();
                }
                if($wholeRide) {
                    $ride->update(['ride_status'=>'cancelled']);
                    RideBooking::whereIn('booking_id',$bookings->pluck('booking_id'))->update(['booking_status'=>'cancelled','updated_at'=>now()]);
                    DriverLocation::forget($ride->ride_id);$cancelled[]=$ride->ride_id;
                } else {
                    $owned=$bookings->where('passenger_id',$memberId);
                    $released=$owned->where('booking_status','accepted')->sum('seats_requested');
                    if($released)$ride->increment('available_seats',$released);
                    RideBooking::whereIn('booking_id',$owned->pluck('booking_id'))->update(['booking_status'=>'cancelled','updated_at'=>now()]);
                }
            }
            if(Schema::hasTable('ride_requests')) {
                RideRequest::where(fn($q)=>$q->where('passenger_id',$memberId)->orWhere('driver_id',$memberId))
                    ->where(fn($q)=>$q->whereIn('request_status',['scheduled','paused','pending'])->orWhere(fn($accepted)=>$accepted->where('request_status','accepted')->whereIn('ride_id',$rides->where('ride_status','!=','in_progress')->pluck('ride_id'))))
                    ->update(['request_status'=>'cancelled','updated_at'=>now()]);
                if($cancelled)RideRequest::whereIn('ride_id',$cancelled)->where('request_status','accepted')->update(['request_status'=>'cancelled','updated_at'=>now()]);
            }
            if(Schema::hasTable('timetable_ride_plans'))DB::table('timetable_ride_plans')->where('user_id',$memberId)->update(['is_enabled'=>false,'updated_at'=>now()]);
            $member->forceFill(['account_status'=>'suspended','status_before_removal'=>$member->account_status,
                'removed_at'=>now(),'removed_by'=>$adminId,'access_version'=>(int)$member->access_version+1])->save();
        },3);
    }
    public static function restore(int $memberId,int $adminId): void
    {
        abort_unless(self::ready(),503);
        DB::transaction(function()use($memberId,$adminId){
            abort_unless(User::whereKey($adminId)->where('role','admin')->where('account_status','active')->exists(),403);
            $member=User::whereKey($memberId)->where('role','student')->lockForUpdate()->firstOrFail();
            if(!$member->removed_at)return;
            $status=in_array($member->status_before_removal,['active','pending','suspended'],true)?$member->status_before_removal:'pending';
            $member->forceFill(['account_status'=>$status,'removed_at'=>null,'removed_by'=>null,'status_before_removal'=>null])->save();
            // Access version stays advanced: an old session must sign in again after restore.
            // Cancelled rides, requests and weekly plans are never restarted by account restore.
        },3);
    }
}
