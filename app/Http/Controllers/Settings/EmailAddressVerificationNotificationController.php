<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EmailAddressVerificationNotificationController extends Controller
{
    /**
     * Send a new verification link to an unverified email address.
     */
    public function store(EmailAddress $emailAddress): RedirectResponse
    {
        Gate::authorize('manage', $emailAddress);

        if (! $emailAddress->isVerified()) {
            $emailAddress->sendVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification link sent to :email.', ['email' => $emailAddress->email])]);

        return to_route('profile.edit');
    }
}
