<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\RespondToTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamMembershipController extends Controller
{
    public function store(RespondToTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $invitation) {
            $team = $invitation->team;

            $team->memberships()->firstOrCreate(['user_id' => $user->id]);

            $invitation->update(['accepted_at' => now()]);

            $user->switchTeam($team);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

        return to_route('dashboard');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $request->user()->switchTeam($team);

        return back();
    }

    public function destroy(Request $request, Team $team, ?User $user = null): RedirectResponse
    {
        $member = $user ?? $request->user();
        $isSelf = $user === null;

        abort_if($team->owner_id === $member->id, 403, __('The team owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $member->id)
            ->delete();

        if ($member->isCurrentTeam($team)) {
            $member->switchTeam($member->fallbackTeam($team) ?? $member->personalTeam());
        }

        Inertia::flash('toast', $isSelf
            ? ['type' => 'success', 'message' => __('You left the team ":name"', ['name' => $team->name])]
            : ['type' => 'success', 'message' => __('Member removed.')]);

        return $isSelf
            ? to_route('teams.index')
            : to_route('teams.edit', ['team' => $team->slug]);
    }
}
