<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class ReadAllNotificationsController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    }
}
