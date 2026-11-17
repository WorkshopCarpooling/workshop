<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordResetController extends Controller
{
    public function showRequest()
    {
        return view('auth.password-recovery');
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:150']]);
        $email = strtolower($data['email']);

        dispatch(static function () use ($email) {
            try {
                $user = User::where('email', $email)->first();
                if ($user && self::canReset($user)) {
                    Password::sendResetLink(['email' => $user->email]);
                }
            } catch (Throwable $exception) {
                Log::warning('Password reset email could not be sent.', ['exception_type' => get_class($exception)]);
            }
        })->afterResponse();

        return redirect()->route('password.request')->with('formSuccess',
            'If an eligible account exists, a password reset link will be sent to that email address.');
    }

    public function showReset(Request $request, string $token)
    {
        $email = $request->query('email');

        return response()->view('auth.password-recovery', [
            'token' => $token,
            'email' => is_string($email) ? $email : '',
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
            'token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]+\z/'],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed', function ($attribute, $value, $fail) {
                if (strlen($value) > 72 || str_contains($value, "\0")) {
                    $fail('Use up to 72 bytes and no null characters.');
                }
            }],
        ]);
        $data['email'] = strtolower($data['email']);

        $updatedUser = DB::transaction(function () use ($data) {
            $user = User::where('email', $data['email'])->lockForUpdate()->first();
            if (!$user || !self::canReset($user)) {
                return null;
            }

            $result = Password::reset($data, function (User $account, string $password) {
                $account->password_hash = Hash::make($password);
                $account->access_version = (int) $account->access_version + 1;
                $account->save();
            });

            return $result === Password::PASSWORD_RESET ? $user->refresh() : null;
        }, 3);

        if (!$updatedUser) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'That link is invalid, expired or already used. Request a new password reset link.',
            ]);
        }

        event(new PasswordReset($updatedUser));
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('formSuccess', 'Password updated. Log in with your new password.');
    }

    private static function canReset(User $user): bool
    {
        return !$user->removed_at && (
            ($user->isActive() && ($user->isAdmin() || $user->isDriver() || $user->isPassenger()))
            || ($user->role === 'student' && $user->account_status === 'pending')
        );
    }
}
