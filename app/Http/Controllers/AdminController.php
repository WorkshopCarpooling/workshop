<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\MemberAccess;

class AdminController extends Controller
{
    public function index(string $section = 'overview')
    {
        abort_unless(Auth::user()?->isAdmin()&&Auth::user()->isActive(),403);
        abort_unless(in_array($section,['overview','live','rides','members','vehicles'],true),404);
        $pageTitle=['overview'=>'Admin overview','live'=>'Live drivers','rides'=>'Ride activity','members'=>'Passengers & drivers','vehicles'=>'Vehicle approvals'][$section];
        $adminUser = Auth::user();

        $directoryReady=MemberAccess::ready();
        $currentMembers=User::where('role','student')->when($directoryReady,fn($q)=>$q->whereNull('removed_at'));

        $pendingMembers = (clone $currentMembers)->where('account_status', 'pending')
            ->orderBy('created_at')
            ->get();

        $pendingVehicles = Vehicle::where('verification_status', 'pending')
            ->when($directoryReady,fn($q)=>$q->whereHas('user',fn($u)=>$u->whereNull('removed_at')))
            ->with('user')
            ->orderBy('created_at')
            ->get();

        $memberCount = (clone $currentMembers)->count();
        $members=null;$directoryCounts=[];$directoryFilters=['type'=>'all','status'=>'','q'=>''];
        if($section==='members') {
            $filters=request()->validate(['type'=>'nullable|in:all,passenger,driver,removed','status'=>'nullable|in:active,pending,suspended','q'=>'nullable|string|max:100']);
            $directoryFilters=['type'=>$filters['type']??'all','status'=>$filters['status']??'','q'=>trim($filters['q']??'')];
            $directoryCounts=['all'=>$memberCount,'passenger'=>(clone $currentMembers)->where('account_type','passenger')->count(),
                'driver'=>(clone $currentMembers)->where('account_type','driver')->count(),
                'removed'=>$directoryReady?User::where('role','student')->whereNotNull('removed_at')->count():0];
            $query=$directoryFilters['type']==='removed'?User::where('role','student')->when($directoryReady,fn($q)=>$q->whereNotNull('removed_at'),fn($q)=>$q->whereRaw('1=0')):clone $currentMembers;
            if(in_array($directoryFilters['type'],['passenger','driver'],true))$query->where('account_type',$directoryFilters['type']);
            if($directoryFilters['status']!==''&&$directoryFilters['type']!=='removed')$query->where('account_status',$directoryFilters['status']);
            if($directoryFilters['q']!==''){$term='%'.$directoryFilters['q'].'%';$query->where(fn($q)=>$q->where('full_name','like',$term)->orWhere('email','like',$term));}
            $members=$query->withCount(['rides as ongoing_driver_trips'=>fn($q)=>$q->where('ride_status','in_progress'),
                'bookings as ongoing_passenger_trips'=>fn($q)=>$q->where('booking_status','accepted')->whereHas('ride',fn($r)=>$r->where('ride_status','in_progress'))])
                ->orderBy('full_name')->orderBy('user_id')->paginate(20)->withQueryString();
        }

        $memberNotice = session('memberNotice', '');
        $memberNoticeType = session('memberNoticeType', '');
        $vehicleNotice = session('vehicleNotice', '');
        $vehicleNoticeType = session('vehicleNoticeType', '');

        $rideQuery=\App\Models\Ride::with('driver')->orderByDesc('departure_at');
        $status=request('status','');
        if(in_array($status,['scheduled','in_progress','completed','cancelled'],true)) $rideQuery->where('ride_status',$status);
        $activity=$section==='rides' ? $rideQuery->limit(100)->get() : collect();
        return view('admin', [
            'directoryReady'=>$directoryReady,'directoryCounts'=>$directoryCounts,'directoryFilters'=>$directoryFilters,'members'=>$members,
            'section'=>$section, 'pageTitle'=>$pageTitle,'activePage'=>'admin_'.$section,'activity'=>$activity,
            'scheduledCount'=>\App\Models\Ride::where('ride_status','scheduled')->count(),
            'activeCount'=>\App\Models\Ride::where('ride_status','in_progress')->count(),
            'carbonSummary'=>\App\Services\CarbonEstimate::summary(),
            'adminUser' => $adminUser,
            'pendingMembers' => $pendingMembers,
            'pendingVehicles' => $pendingVehicles,
            'memberCount' => $memberCount,
            'memberNotice' => $memberNotice,
            'memberNoticeType' => $memberNoticeType,
            'vehicleNotice' => $vehicleNotice,
            'vehicleNoticeType' => $vehicleNoticeType,
        ]);
    }

    public function handleAction(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin()&&Auth::user()->isActive(),403);
        $action = $request->input('action');

        return match ($action) {
            'approve_member' => $this->approveMember($request),
            'approve_vehicle', 'reject_vehicle' => $this->reviewVehicle($request),
            default => redirect('admin.php')->with('memberNotice', 'That action is not valid.')->with('memberNoticeType', 'error'),
        };
    }

    protected function approveMember(Request $request)
    {
        $memberId = filter_var($request->input('user_id'), FILTER_VALIDATE_INT);

        if (!$memberId) {
            return redirect('admin/members')->with('memberNotice', 'That account could not be identified.')->with('memberNoticeType', 'error');
        }

        $approved = DB::transaction(function()use($memberId){
            $user=User::where('user_id',$memberId)->where('account_status','pending')->where('role','student')
                ->when(MemberAccess::ready(),fn($query)=>$query->whereNull('removed_at'))->lockForUpdate()->first();
            if(!$user)return false;
            $user->update(['account_status'=>'active']);return true;
        },3);
        if (!$approved) {
            return redirect('admin/members')->with('memberNotice', 'This account is no longer pending.')->with('memberNoticeType', 'error');
        }

        return redirect('admin/members')->with('memberNotice', 'Account approved. The member can now log in.')->with('memberNoticeType', 'success');
    }

    protected function reviewVehicle(Request $request)
    {
        $adminUser = Auth::user();
        $vehicleId = filter_var($request->input('vehicle_id'), FILTER_VALIDATE_INT);
        $action = $request->input('action');

        if (!$vehicleId) {
            return redirect('admin/vehicles')->with('vehicleNotice', 'That vehicle could not be identified.')->with('vehicleNoticeType', 'error');
        }

        $vehicle = Vehicle::where('vehicle_id', $vehicleId)
            ->where('verification_status', 'pending')
            ->first();

        if (!$vehicle) {
            return redirect('admin/vehicles')->with('vehicleNotice', 'This vehicle is no longer waiting for review.')->with('vehicleNoticeType', 'error');
        }

        if(MemberAccess::ready()&&$vehicle->user?->removed_at) return redirect('admin/vehicles')->with('vehicleNotice','Restore this member’s access before reviewing their vehicle.')->with('vehicleNoticeType','error');

        $nextStatus = $action === 'approve_vehicle' ? 'approved' : 'rejected';
        $adminNote = trim((string) $request->input('admin_note', ''));

        $vehicle->update([
            'verification_status' => $nextStatus,
            'admin_note' => $adminNote !== '' ? mb_substr($adminNote, 0, 255) : null,
            'reviewed_by' => $adminUser->user_id,
            'reviewed_at' => now(),
        ]);

        $msg = $nextStatus === 'approved'
            ? 'Vehicle approved. The driver can now publish rides with it.'
            : 'Vehicle declined. The driver can update the details and submit again.';

        return redirect('admin/vehicles')->with('vehicleNotice', $msg)->with('vehicleNoticeType', 'success');
    }
}
