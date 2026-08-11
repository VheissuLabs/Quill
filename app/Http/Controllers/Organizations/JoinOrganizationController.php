<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\AcceptInvitation;
use App\Actions\Organizations\JoinFromInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\JoinOrganizationRequest;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class JoinOrganizationController extends Controller
{
    public function show(OrganizationInvitation $invitation): Response|RedirectResponse
    {
        if (! $invitation->isPending()) {
            return $this->rejected($invitation);
        }

        /**
         * Someone who already has an account signs in and accepts from their
         * dashboard, so there is nothing to set up here.
         */
        if ($this->accountExistsFor($invitation)) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Log in to accept your invitation.'),
            ]);

            return to_route('login');
        }

        $invitation->load(['organization', 'client', 'inviter']);

        return Inertia::render('auth/Join', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'invitation' => [
                'code' => $invitation->code,
                'email' => $invitation->email,
                'inviterName' => $invitation->inviter->name,
                'organizationName' => $invitation->organization->name,
                'clientName' => $invitation->client?->name,
            ],
        ]);
    }

    public function store(
        JoinOrganizationRequest $request,
        OrganizationInvitation $invitation,
        JoinFromInvitation $joinFromInvitation,
        AcceptInvitation $acceptInvitation,
    ): RedirectResponse {
        if (! $invitation->isPending()) {
            return $this->rejected($invitation);
        }

        if ($this->accountExistsFor($invitation)) {
            return to_route('login');
        }

        $user = $joinFromInvitation->handle(
            $invitation,
            $request->validated('name'),
            $request->validated('password'),
        );

        Auth::login($user);

        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("You've joined :organizationName.", [
                'organizationName' => $invitation->organization->name,
            ]),
        ]);

        return to_route('home');
    }

    protected function accountExistsFor(OrganizationInvitation $invitation): bool
    {
        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])->exists();
    }

    protected function rejected(OrganizationInvitation $invitation): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $invitation->isAccepted()
                ? __('This invitation has already been accepted.')
                : __('This invitation has expired.'),
        ]);

        return to_route('login');
    }
}
