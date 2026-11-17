<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);
        $user = $request->user();
        if(!$user->isActive()||!($user->isAdmin()||$user->role==='student')) {
            \Illuminate\Support\Facades\Auth::logout();
            if($request->hasSession()){$request->session()->invalidate();$request->session()->regenerateToken();}
            abort(403,'RideUTeM is available to approved UTeM students and administrators only.');
        }
        if($request->hasSession()&&(int)$request->session()->get('member_access_version.'.$user->user_id,0)!==(int)($user->access_version??0)) {
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();$request->session()->regenerateToken();
            throw new \Illuminate\Auth\AuthenticationException('Your access changed. Please sign in again.',$guards,$this->redirectTo($request));
        }
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return url('login.php');
        }
    }
}
