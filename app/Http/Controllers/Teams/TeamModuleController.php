<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamModulesRequest;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TeamModuleController extends Controller
{
    public function update(UpdateTeamModulesRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        $team->updateModuleSettings($request->validated());

        if (! $request->boolean('sprints_enabled')) {
            $team->returnSprintWorkToBacklog();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team modules updated.')]);

        return back();
    }
}
