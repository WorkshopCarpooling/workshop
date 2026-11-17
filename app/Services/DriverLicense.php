<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DriverLicense
{
    public static function store(Request $request): string
    {
        $data = $request->validate([
            'driver_license' => ['bail', 'required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp',
                'dimensions:max_width=4096,max_height=4096'],
        ], [
            'driver_license.required' => 'Upload a clear image of your driving licence.',
            'driver_license.uploaded' => 'The licence could not be uploaded. Choose an image up to 2 MB.',
        ], ['driver_license' => 'driving licence']);

        $path = Storage::disk('local')->putFile('driver-licences', $data['driver_license']);
        if ($path === false) {
            throw ValidationException::withMessages(['driver_license' => 'The licence could not be saved. Please try again.']);
        }

        return $path;
    }
}
