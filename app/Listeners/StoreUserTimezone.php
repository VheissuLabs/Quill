<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

class StoreUserTimezone
{
    public function __construct(private Request $request) {}

    public function handle(Login|Registered $event): void
    {
        $timezone = $this->request->input('timezone');
        $user = $event->user;

        if (! $user instanceof User || ! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        if ($user->timezone !== $timezone) {
            $user->forceFill(['timezone' => $timezone])->save();
        }
    }
}
