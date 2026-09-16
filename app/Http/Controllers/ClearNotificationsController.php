<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClearNotificationsController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back();
    }
}
