<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'avatar' => ['bail', 'nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4096,max_height=4096'],
        ]);

        $upload = $data['avatar'] ?? null;
        unset($data['avatar']);
        $disk = Storage::disk('local');
        $newAvatar = null;
        if ($upload !== null) {
            $newAvatar = $disk->putFile('avatars', $upload);
            if ($newAvatar === false) {
                throw ValidationException::withMessages(['avatar' => 'Your photo could not be saved. Please try again.']);
            }
        }

        try {
            $oldAvatar = DB::transaction(function () use ($request, $data, $newAvatar) {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
                abort_unless($user->isActive(), 403);

                $user->fill($data);
                $oldAvatar = null;
                if ($newAvatar !== null) {
                    $oldAvatar = $user->avatar_path;
                    $user->avatar_path = $newAvatar;
                }
                $user->save();

                return $oldAvatar;
            });
        } catch (Throwable $exception) {
            if ($newAvatar !== null) {
                $disk->delete($newAvatar);
            }
            throw $exception;
        }

        if ($oldAvatar !== null) {
            $disk->delete($oldAvatar);
        }

        return redirect()->route('profile.edit')->with('profileNotice', 'Profile saved.');
    }

    public function updatePassword(Request $request)
    {
        $destination = route('profile.edit').'#change-password';

        try {
            $data = $request->validate([
                'current_password' => ['required', 'string'],
                'password' => ['bail', 'required', 'string', 'min:8', function ($attribute, $value, $fail) {
                    if (strlen($value) > 72 || str_contains($value, "\0")) {
                        $fail('Use a new password of up to 72 bytes with no null characters.');
                    }
                }],
                'password_confirmation' => ['required', 'string', 'same:password'],
            ], [
                'password_confirmation.same' => 'The new passwords do not match.',
            ], [
                'password' => 'new password',
                'password_confirmation' => 'new password confirmation',
            ]);

            $accessVersion = DB::transaction(function () use ($request, $data) {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
                abort_unless($user->isActive(), 403);

                if (!Hash::check($data['current_password'], $user->password_hash)) {
                    throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
                }
                if (Hash::check($data['password'], $user->password_hash)) {
                    throw ValidationException::withMessages(['password' => 'Choose a password different from your current password.']);
                }

                $user->password_hash = Hash::make($data['password']);
                if (isset($user->access_version)) {
                    $user->access_version++;
                }
                $user->save();

                return (int) $user->access_version;
            });
        } catch (ValidationException $exception) {
            throw $exception->errorBag('changePassword')->redirectTo($destination);
        }

        $request->session()->regenerate(true);
        $request->session()->put('member_access_version.'.$request->user()->getKey(), $accessVersion);

        return redirect()->to($destination)->with('passwordNotice', 'Password updated.');
    }

    public function avatar(Request $request)
    {
        $path = $request->user()->avatar_path;
        abort_unless($path, 404);

        $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
        abort_unless($mime, 404);

        $photo = Storage::disk('local')->get($path);
        abort_if($photo === null, 404);

        return response($photo, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
