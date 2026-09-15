<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\AddTeamMemberRequest;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    public function store(AddTeamMemberRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->members()->attach($request->validated('user_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added to team.')]);

        return back();
    }

    public function destroy(Organization $currentOrganization, Team $team, User $member): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->members()->detach($member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed from team.')]);

        return back();
    }
}
