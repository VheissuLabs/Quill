<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationReadController extends Controller
{
    public function store(DatabaseNotification $notification): RedirectResponse
    {
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? url()->previous());
    }
}
