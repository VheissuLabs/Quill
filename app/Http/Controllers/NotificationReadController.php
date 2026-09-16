<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationReadController extends Controller
{
    public function store(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable()->is(auth()->user()), 404);

        $notification->markAsRead();

        return redirect($notification->data['url'] ?? url()->previous());
    }
}
