<?php
namespace App\Http\Controllers;

use App\Services\MemberAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMemberController extends Controller
{
    public function remove(Request $request,int $member)
    {
        abort_unless(Auth::user()?->isAdmin()&&Auth::user()->isActive(),403);
        MemberAccess::remove($member,(int)Auth::id());
        return redirect('admin/members')->with('memberNotice','Member removed. Access is blocked and unstarted bookings are cancelled. Past trip records are kept.')->with('memberNoticeType','success');
    }
    public function restore(Request $request,int $member)
    {
        abort_unless(Auth::user()?->isAdmin()&&Auth::user()->isActive(),403);
        MemberAccess::restore($member,(int)Auth::id());
        return redirect('admin/members?type=removed')->with('memberNotice','Member restored to their previous account status. They must sign in again. Cancelled trips stay cancelled.')->with('memberNoticeType','success');
    }
}
