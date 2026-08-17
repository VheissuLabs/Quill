<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    public function destroy(Request $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        abort_if($team->owner_id === $user->id, 403, __('The team owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
