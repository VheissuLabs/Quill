<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class VerifyEmailAddressController extends Controller
{
    /**
     * Mark the email address as verified from its signed verification link.
     */
    public function __invoke(EmailAddress $emailAddress, string $hash): RedirectResponse
    {
        Gate::authorize('manage', $emailAddress);

        abort_unless(hash_equals(sha1($emailAddress->email), $hash), 403);

        if (! $emailAddress->isVerified()) {
            $emailAddress->markAsVerified();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':email verified.', ['email' => $emailAddress->email])]);

        return to_route('profile.edit');
    }
}
