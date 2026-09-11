<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreEmailAddressRequest;
use App\Models\EmailAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EmailAddressController extends Controller
{
    /**
     * Add an email address to the user's account and send it a verification link.
     */
    public function store(StoreEmailAddressRequest $request): RedirectResponse
    {
        $emailAddress = $request->user()->emailAddresses()->create($request->validated());

        $emailAddress->sendVerificationNotification();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification link sent to :email.', ['email' => $emailAddress->email])]);

        return to_route('profile.edit');
    }

    /**
     * Remove an email address from the user's account.
     */
    public function destroy(EmailAddress $emailAddress): RedirectResponse
    {
        Gate::authorize('manage', $emailAddress);

        $emailAddress->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email address removed.')]);

        return to_route('profile.edit');
    }
}
