<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationReadController extends Controller
{
    public function store(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable()->is($request->user()), 404);

        $notification->markAsRead();

        return redirect($notification->data['url'] ?? url()->previous());
    }
}
