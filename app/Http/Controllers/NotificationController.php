<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function destroy(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable()->is(auth()->user()), 404);

        $notification->delete();

        return back();
    }
}
