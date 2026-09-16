<?php

namespace App\Http\Controllers\Sprints;

use App\Enums\IssueStatus;
use App\Enums\SprintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\SprintRequest;
use App\Models\BoardColumn;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Sprint;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Sprint::class, $currentOrganization]);

        $owner = $request->user()->boardOwnerFor($currentOrganization);

        $sprints = $owner->sprints()
            ->with('owner')
            ->withCount([
                'issues',
                'issues as done_issues_count' => fn ($query) => $query->where('status', IssueStatus::Done),
            ])
            ->withSum('issues', 'points')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Sprint $sprint) => $this->sprintSummary($sprint));

        return Inertia::render('sprints/Index', [
            'sprints' => $sprints,
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function store(SprintRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [Sprint::class, $currentOrganization]);

        $sprint = $request->user()->boardOwnerFor($currentOrganization)->sprints()->create([
            ...$request->validated(),
            'status' => SprintStatus::Planned,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint created.')]);

        return to_route('sprints.show', ['sprint' => $sprint]);
    }

    public function show(Request $request, Organization $currentOrganization, Sprint $sprint): Response
    {
        Gate::authorize('view', $sprint);

        $sprint->load('owner.columns')->loadCount([
            'issues',
            'issues as done_issues_count' => fn ($query) => $query->where('status', IssueStatus::Done),
        ])->loadSum('issues', 'points');

        $issues = $sprint->issues()
            ->with(['project', 'assignee'])
            ->orderBy('position')
            ->orderBy('number')
            ->get()
            ->map(fn (Issue $issue) => $this->cardSummary($issue));

        return Inertia::render('sprints/Show', [
            'sprint' => $this->sprintSummary($sprint),
            'issues' => $issues,
            'columns' => $sprint->columns()->map(fn (BoardColumn $column) => [
                'id' => $column->id,
                'name' => $column->name,
                'status' => $column->status->value,
            ]),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function update(SprintRequest $request, Organization $currentOrganization, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('update', $sprint);

        $sprint->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint updated.')]);

        return back();
    }

    public function destroy(Organization $currentOrganization, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('delete', $sprint);

        $ownPage = route('sprints.show', ['current_organization' => $currentOrganization, 'sprint' => $sprint]);

        $sprint->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint deleted.')]);

        if (url()->previous() === $ownPage) {
            return to_route('sprints.index');
        }

        return back(fallback: route('sprints.index'));
    }

    /** @return array<string, mixed> */
    private function cardSummary(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'number' => $issue->number,
            'reference' => $issue->reference(),
            'title' => $issue->title,
            'status' => $issue->status->value,
            'boardColumnId' => $issue->board_column_id,
            'priority' => $issue->priority->value,
            'priorityLabel' => $issue->priority->label(),
            'points' => $issue->points,
            'projectName' => $issue->project->name,
            'assigneeName' => $issue->assignee?->name,
            'hasFeature' => $issue->feature_id !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function sprintSummary(Sprint $sprint): array
    {
        return [
            'id' => $sprint->id,
            'name' => $sprint->name,
            'status' => $sprint->status->value,
            'statusLabel' => $sprint->status->label(),
            'startsAt' => $sprint->starts_at->toDateString(),
            'endsAt' => $sprint->ends_at->toDateString(),
            'owner' => [
                'name' => $sprint->owner->name,
                'slug' => $sprint->owner->slug,
                'isTeam' => $sprint->owner instanceof Team,
            ],
            'issuesCount' => $sprint->issues_count,
            'doneIssuesCount' => $sprint->done_issues_count,
            'points' => (int) $sprint->issues_sum_points,
        ];
    }
}
