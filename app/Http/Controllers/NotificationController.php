<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function destroy(DatabaseNotification $notification): RedirectResponse
    {
        $notification->delete();

        return back();
    }
}
