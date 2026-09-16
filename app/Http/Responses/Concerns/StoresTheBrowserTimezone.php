<?php

namespace App\Http\Responses\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait StoresTheBrowserTimezone
{
    protected function storeSubmittedTimezone(Request $request): void
    {
        $timezone = $request->input('timezone');
        $user = $request->user();

        if (! $user instanceof User || ! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        if ($user->timezone !== $timezone) {
            $user->forceFill(['timezone' => $timezone])->save();
        }
    }
}
