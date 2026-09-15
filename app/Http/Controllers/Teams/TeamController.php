<?php

namespace App\Http\Controllers\Teams;

use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\BoardColumn;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Team::class, $currentOrganization]);

        return Inertia::render('teams/Index', [
            'teams' => $currentOrganization->teams()
                ->withCount('members')
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'membersCount' => $team->members_count,
                ]),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function store(SaveTeamRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [Team::class, $currentOrganization]);

        $team = $currentOrganization->teams()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function edit(Request $request, Organization $currentOrganization, Team $team): Response
    {
        Gate::authorize('view', $team);

        $memberIds = $team->members()->pluck('users.id');

        return Inertia::render('teams/Edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'members' => $team->members()
                ->orderBy('name')
                ->get()
                ->map(fn (User $member) => $this->memberSummary($member)),
            'availableMembers' => $currentOrganization->members()
                ->whereKeyNot($memberIds)
                ->orderBy('name')
                ->get()
                ->map(fn (User $member) => $this->memberSummary($member)),
            'columns' => $team->columns()->withCount('issues')->get()->map(fn (BoardColumn $column) => [
                'id' => $column->id,
                'name' => $column->name,
                'status' => $column->status->value,
                'issuesCount' => $column->issues_count,
            ]),
            'columnStatuses' => collect(IssueStatus::columnStatuses())
                ->map(fn (IssueStatus $status) => ['value' => $status->value, 'label' => $status->label()]),
            'otherTeams' => $currentOrganization->teams()
                ->whereKeyNot($team->id)
                ->orderBy('name')
                ->get()
                ->map(fn (Team $other) => ['id' => $other->id, 'name' => $other->name]),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function switch(Request $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        abort_unless($request->user()->switchTeam($team), 403);

        return back();
    }

    public function update(SaveTeamRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function destroy(DeleteTeamRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        Gate::authorize('delete', $team);

        $target = $request->filled('target_team_id')
            ? $currentOrganization->teams()->whereKey($request->integer('target_team_id'))->firstOrFail()
            : $currentOrganization;

        $team->mergeInto($target);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted, its work moved to ":name".', ['name' => $target->name])]);

        return to_route('teams.index');
    }

    /**
     * @return array{id: int, name: string, email: string, avatar: string|null}
     */
    private function memberSummary(User $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->avatar ?? null,
        ];
    }
}
