<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreEmailAddressRequest;
use App\Models\EmailAddress;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmailAddressController extends Controller
{
    public function store(StoreEmailAddressRequest $request): RedirectResponse
    {
        $emailAddress = $request->user()->emailAddresses()->create($request->validated());

        $emailAddress->sendVerificationNotification();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification link sent to :email.', ['email' => $emailAddress->email])]);

        return to_route('profile.edit');
    }

    public function destroy(EmailAddress $emailAddress): RedirectResponse
    {
        $emailAddress->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email address removed.')]);

        return to_route('profile.edit');
    }
}
