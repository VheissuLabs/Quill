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
        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
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
        $declinedByInvitee = mb_strtolower($invitation->email) === mb_strtolower($request->user()->email);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => $declinedByInvitee
            ? __('Invitation declined.')
            : __('Invitation cancelled.')]);

        return $declinedByInvitee
            ? to_route('dashboard')
            : to_route('teams.edit', ['team' => $team->slug]);
    }
}
