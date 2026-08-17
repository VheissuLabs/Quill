<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamInvitationRequest;
use App\Http\Requests\Teams\RespondToTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    public function store(CreateTeamInvitationRequest $request, Team $team): RedirectResponse
    {
        $invitation = $team->invitations()->create([
            'email' => $request->validated('email'),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function destroy(
        RespondToTeamInvitationRequest $request,
        Team $team,
        TeamInvitation $invitation,
    ): RedirectResponse {
        abort_if($invitation->team_id !== $team->id, 404);

        $cancelledByMember = $request->user()->belongsToTeam($team);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => $cancelledByMember
            ? __('Invitation cancelled.')
            : __('Invitation declined.')]);

        return $cancelledByMember
            ? to_route('teams.edit', ['team' => $team->slug])
            : to_route('dashboard');
    }
}
