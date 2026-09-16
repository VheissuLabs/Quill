<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailAddress;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmailAddressVerificationNotificationController extends Controller
{
    public function store(EmailAddress $emailAddress): RedirectResponse
    {
        if (! $emailAddress->isVerified()) {
            $emailAddress->sendVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification link sent to :email.', ['email' => $emailAddress->email])]);

        return to_route('profile.edit');
    }
}
