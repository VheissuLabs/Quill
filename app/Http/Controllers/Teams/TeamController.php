<?php

namespace App\Http\Controllers\Teams;

use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\TeamRequest;
use App\Http\Resources\BoardColumnResource;
use App\Http\Resources\TeamListResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserContactResource;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Organization $currentOrganization): Response
    {
        return Inertia::render('teams/Index', [
            'teams' => TeamListResource::collection($currentOrganization->teams()->withCount('members')->orderBy('name')->get()),
        ]);
    }

    public function store(TeamRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $team = $currentOrganization->teams()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function edit(Organization $currentOrganization, Team $team): Response
    {
        return Inertia::render('teams/Edit', [
            'team' => TeamResource::make($team),
            'members' => UserContactResource::collection($team->members()->orderBy('name')->get()),
            'availableMembers' => UserContactResource::collection(
                $currentOrganization->members()->whereKeyNot($team->members()->pluck('users.id'))->orderBy('name')->get()
            ),
            'columns' => BoardColumnResource::collection($team->columns()->withCount('issues')->get()),
            'columnStatuses' => collect(IssueStatus::columnStatuses())
                ->map(fn (IssueStatus $status) => ['value' => $status->value, 'label' => $status->label()]),
            'otherTeams' => TeamResource::collection($currentOrganization->teams()->whereKeyNot($team->id)->orderBy('name')->get()),
        ]);
    }

    public function update(TeamRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function destroy(DeleteTeamRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        $target = $request->filled('target_team_id')
            ? $currentOrganization->teams()->whereKey($request->integer('target_team_id'))->firstOrFail()
            : $currentOrganization;

        $team->mergeInto($target);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted, its work moved to ":name".', ['name' => $target->name])]);

        return to_route('teams.index');
    }
}
