<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable()->is($request->user()), 404);

        $notification->markAsRead();

        return redirect($notification->data['url'] ?? url()->previous());
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function destroy(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable()->is($request->user()), 404);

        $notification->delete();

        return back();
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back();
    }
}
