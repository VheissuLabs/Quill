<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

/**
 * Remembers the browser's timezone, sent as a hidden field by the login and registration forms,
 * so dates in email and other server-rendered output can be shown in the user's local time.
 */
class StoreUserTimezone
{
    public function __construct(private Request $request)
    {
        //
    }

    /**
     * Handle the event.
     */
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
