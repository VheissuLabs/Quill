<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\AcceptInvitation;
use App\Actions\Organizations\JoinFromInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\RespondToOrganizationInvitationRequest;
use App\Http\Requests\Organizations\StoreOrganizationMembershipRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationMembershipController extends Controller
{
    public function create(OrganizationInvitation $invitation): Response|RedirectResponse
    {
        if (! $invitation->isPending()) {
            return $this->rejected($invitation);
        }

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
        StoreOrganizationMembershipRequest $request,
        OrganizationInvitation $invitation,
        JoinFromInvitation $joinFromInvitation,
        AcceptInvitation $acceptInvitation,
    ): RedirectResponse {
        $user = $request->user();

        if ($user !== null) {
            $acceptInvitation->handle($user, $invitation);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

            return to_route('dashboard', ['current_team' => $user->currentTeam?->slug]);
        }

        if (! $invitation->isPending()) {
            return $this->rejected($invitation);
        }

        if ($this->accountExistsFor($invitation)) {
            return to_route('login');
        }

        Auth::login($joinFromInvitation->handle(
            $invitation,
            $request->validated('name'),
            $request->validated('password'),
        ));

        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("You've joined :organizationName.", [
                'organizationName' => $invitation->organization->name,
            ]),
        ]);

        return to_route('home');
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $request->user()->switchOrganization($organization);

        return back();
    }

    public function destroy(
        RespondToOrganizationInvitationRequest $request,
        OrganizationInvitation $invitation,
    ): RedirectResponse {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return to_route('dashboard', ['current_team' => $request->user()->currentTeam?->slug]);
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
