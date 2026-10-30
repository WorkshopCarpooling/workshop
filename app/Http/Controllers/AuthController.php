<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectForUser(Auth::user());
        }

        return view('auth.login', [
            'isSignup' => false,
            'title' => 'Welcome back',
        ]);
    }

    public function login(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return back()->withInput($request->only('email'))->with('formError', 'Enter your email address and password.');
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password_hash)) {
            return back()->withInput($request->only('email'))->with('formError', 'The email or password is incorrect.');
        }

        if (!$user->isActive()) {
            return back()->withInput($request->only('email'))->with('formError', $user->account_status==='pending'?'This account is waiting for admin approval.':'This account is not active. Contact the administrator.');
        }

        if (!$user->isAdmin() && $user->role !== 'student') {
            return back()->withInput($request->only('email'))->with('formError', 'RideUTeM is available to approved UTeM students only.');
        }

        Auth::login($user, $request->filled('remember'));
        $request->session()->regenerate();
        $request->session()->put('member_access_version.'.$user->user_id,(int)($user->access_version??0));

        return $this->redirectForUser($user)->with('workspaceEntrance', true);
    }

    public function showSignup()
    {
        if (Auth::check()) {
            return $this->redirectForUser(Auth::user());
        }

        return view('auth.signup', [
            'isSignup' => true,
            'title' => 'Create your account',
        ]);
    }

    public function signup(Request $request)
    {
        $fullName = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $role = (string) $request->input('memberType', '');
        $accountType = (string) $request->input('accountType', '');
        $password = (string) $request->input('password', '');
        $confirmation = (string) $request->input('confirmPassword', '');

        if ($fullName === '' || mb_strlen($fullName) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withInput($request->except(['password', 'confirmPassword']))->with('formError', 'Enter your name and a valid email address.');
        }

        if ($role !== 'student') {
            return back()->withInput($request->except(['password', 'confirmPassword']))->with('formError', 'Registration is for UTeM students only. Admin accounts are assigned separately.');
        }

        if (!in_array($accountType, ['passenger', 'driver'], true)) {
            return back()->withInput($request->except(['password', 'confirmPassword']))->with('formError', 'Choose whether you are joining as a passenger or driver.');
        }

        if (strlen($password) < 8) {
            return back()->withInput($request->except(['password', 'confirmPassword']))->with('formError', 'Choose a password with at least 8 characters.');
        }

        if (!hash_equals($password, $confirmation)) {
            return back()->withInput($request->except(['password', 'confirmPassword']))->with('formError', 'Your passwords do not match yet.');
        }

        if (User::where('email', $email)->exists()) {
            return back()->withInput($request->except(['password', 'confirmPassword']))->with('formError', 'An account with this email already exists.');
        }

        User::create([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => Hash::make($password),
            'role' => $role,
            'account_type' => $accountType,
            'email_verified' => false,
            'account_status' => 'pending',
        ]);

        return back()->with('formSuccess', 'Your account request was created. An administrator must approve it before you can log in.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('login.php');
    }

    protected function redirectForUser(User $user)
    {
        if ($user->isAdmin()) {
            return redirect('admin.php');
        }

        if ($user->isDriver()) {
            return redirect('driver.php');
        }

        return redirect('dashboard.php');
    }
}
