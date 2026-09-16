<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class ClearNotificationsController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        auth()->user()->notifications()->delete();

        return back();
    }
}
