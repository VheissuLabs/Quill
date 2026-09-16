<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailAddress;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VerifyEmailAddressController extends Controller
{
    public function __invoke(EmailAddress $emailAddress): RedirectResponse
    {
        if (! $emailAddress->isVerified()) {
            $emailAddress->markAsVerified();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':email verified.', ['email' => $emailAddress->email])]);

        return to_route('profile.edit');
    }
}
