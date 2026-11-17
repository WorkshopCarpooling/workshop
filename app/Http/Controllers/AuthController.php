<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DriverLicense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

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
            if (!$user->removed_at && $user->role === 'student'
                && in_array($user->applicationStatus(), ['pending', 'resubmit', 'declined'], true)) {
                $request->session()->regenerate(true);
                $request->session()->put('application_access', [
                    'id' => $user->getKey(),
                    'version' => (int) $user->access_version,
                    'expires' => now()->addMinutes(15)->timestamp,
                ]);

                return redirect()->route('application.show');
            }

            return back()->withInput($request->only('email'))->with('formError', $user->account_status==='pending'?'This account is waiting for admin approval.':'This account is not active. Contact the administrator.');
        }

        if (!$user->isAdmin() && $user->role !== 'student') {
            return back()->withInput($request->only('email'))->with('formError', 'RideUTeM is available to approved UTeM students only.');
        }

        Auth::login($user, $request->filled('remember'));
        $request->session()->regenerate();
        $request->session()->forget('application_access');
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

        $user = new User([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => Hash::make($password),
            'role' => $role,
            'account_type' => $accountType,
            'email_verified' => false,
            'account_status' => 'pending',
        ]);

        $disk = Storage::disk('local');
        try {
            if ($accountType === 'driver') {
                $data = $request->validate([
                    'avatar' => ['bail', 'required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4096,max_height=4096'],
                ], [
                    'avatar.required' => 'Upload a driver profile photo to create a driver account.',
                    'avatar.uploaded' => 'Your photo could not be uploaded. Choose an image up to 2 MB.',
                ], ['avatar' => 'driver photo']);

                $path = $disk->putFile('avatars', $data['avatar']);
                if ($path === false) {
                    throw ValidationException::withMessages(['avatar' => 'Your photo could not be saved. Please try again.']);
                }

                $user->avatar_path = $path;
                $user->driver_license_path = DriverLicense::store($request);
            }
            $user->save();
        } catch (Throwable $exception) {
            $disk->delete(array_filter([$user->avatar_path, $user->driver_license_path]));
            throw $exception;
        }

        return back()->with('formSuccess', 'Your account request was created. An administrator must approve it before you can log in.');
    }

    public function showApplication(Request $request)
    {
        $applicant = $this->applicant($request);
        if (!$applicant) {
            return redirect()->route('login');
        }

        return response()->view('auth.application', ['applicant' => $applicant])
            ->header('Cache-Control', 'private, no-store');
    }

    public function resubmitLicense(Request $request)
    {
        $applicant = $this->applicant($request);
        if (!$applicant) {
            return redirect()->route('login');
        }
        abort_unless($applicant->canUploadLicense(), 409, 'This application is not accepting a licence.');

        $path = DriverLicense::store($request);
        $disk = Storage::disk('local');
        try {
            $previousPath = DB::transaction(function () use ($applicant, $path) {
                $current = User::query()->lockForUpdate()->findOrFail($applicant->getKey());
                abort_unless($current->canUploadLicense() && $current->access_version === $applicant->access_version,
                    409, 'This application has changed. Check its current status.');
                $previousPath = $current->driver_license_path;
                $current->driver_license_path = $path;
                $current->save();

                return $previousPath;
            }, 3);
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
        if ($previousPath) {
            $disk->delete($previousPath);
        }

        return redirect()->route('application.show')->with('formSuccess', 'Licence submitted for admin review.');
    }

    private function applicant(Request $request): ?User
    {
        $access = $request->session()->get('application_access');
        if (!$access || now()->timestamp >= $access['expires'] || Auth::check()) {
            return null;
        }

        $user = User::where('role', 'student')->find($access['id']);
        if (!$user || $user->removed_at || (int) $user->access_version !== $access['version']) {
            $request->session()->forget('application_access');
            return null;
        }

        return $user;
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
