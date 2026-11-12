<?php

namespace App\Services;

final class LocationLabel
{
    public static function short(?string $label): string
    {
        // Keep the named place; routing and matching continue to use the saved pins.
        return trim(explode(',', trim($label ?? ''), 2)[0]);
    }
}
