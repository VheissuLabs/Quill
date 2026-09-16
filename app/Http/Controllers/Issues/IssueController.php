<?php

namespace App\Http\Controllers\Issues;

use App\Actions\Issues\CreateIssue;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\OrganizationPermission;
use App\Enums\SprintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\SaveIssueRequest;
use App\Http\Resources\ActivitySummary;
use App\Models\BoardColumn;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class IssueController extends Controller
{
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Issue::class, $currentOrganization]);

        $user = $request->user();
        $team = $user->currentTeamFor($currentOrganization);
        $projectIds = $currentOrganization->projects()->visibleTo($user)->pluck('projects.id');

        $filters = [
            'status' => IssueStatus::tryFrom((string) $request->query('status'))?->value,
            'project' => $request->string('project')->toString() ?: null,
            'assignee' => $request->string('assignee')->toString() ?: null,
        ];

        $issues = Issue::query()
            ->whereIn('project_id', $projectIds)
            ->with(['project', 'assignee', 'sprint', 'team', 'boardColumn'])
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(! $filters['status'], fn (Builder $query) => $query->whereNotIn('status', [IssueStatus::Done, IssueStatus::Cancelled]))
            ->when($filters['project'], fn (Builder $query, string $slug) => $query->whereHas('project', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($team, fn (Builder $query, Team $team) => $query->where(fn (Builder $query) => $query->where('team_id', $team->id)->orWhereNull('team_id')))
            ->whereNull('sprint_id')
            ->when($filters['assignee'] === 'me', fn (Builder $query) => $query->where('assignee_id', $user->id))
            ->orderBy('position')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->latest()
            ->get()
            ->map(fn (Issue $issue) => $this->issueSummary($issue));

        return Inertia::render('issues/Index', [
            'issues' => $issues,
            'filters' => $filters,
            'activity' => $user->hasOrganizationPermission($currentOrganization, OrganizationPermission::ViewActivity)
                ? $this->recentActivity($projectIds, $team)
                : [],
            ...$this->formOptions($currentOrganization, $user, $team),
            'permissions' => $user->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function store(SaveIssueRequest $request, Organization $currentOrganization, CreateIssue $createIssue): RedirectResponse
    {
        Gate::authorize('create', [Issue::class, $currentOrganization]);

        $project = $currentOrganization->projects()->whereKey($request->integer('project_id'))->firstOrFail();

        Gate::authorize('view', $project);

        $issue = new Issue([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'priority' => IssuePriority::from($request->validated('priority')),
            'points' => $request->filled('points') ? $request->integer('points') : null,
            'team_id' => $request->integer('team_id') ?: null,
            'assignee_id' => $request->integer('assignee_id') ?: null,
            'created_by' => $request->user()->getKey(),
        ]);

        $issue->sprint()->associate(Sprint::find($request->integer('sprint_id')));

        $issue = $createIssue->handle($project, $issue);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':reference created.', ['reference' => $issue->reference()])]);

        return to_route('issues.show', ['issue' => $issue]);
    }

    public function show(Request $request, Organization $currentOrganization, Issue $issue): Response
    {
        Gate::authorize('view', $issue);

        $issue->load(['project', 'team.columns', 'sprint', 'boardColumn.owner', 'assignee', 'creator', 'feature.author']);

        return Inertia::render('issues/Show', [
            'issue' => [
                ...$this->issueSummary($issue),
                'description' => $issue->description,
                'teamId' => $issue->team_id,
                'sprintId' => $issue->sprint_id,
                'boardColumnId' => $issue->board_column_id,
                'columns' => ($issue->team ?? $request->user()->boardOwnerFor($currentOrganization))
                    ->columns
                    ->map(fn (BoardColumn $column) => [
                        'id' => $column->id,
                        'name' => $column->name,
                        'status' => $column->status->value,
                    ]),
                'assigneeId' => $issue->assignee_id,
                'creatorName' => $issue->creator?->name,
                'completedAt' => $issue->completed_at?->toISOString(),
            ],
            'feature' => $issue->feature ? [
                'id' => $issue->feature->id,
                'title' => $issue->feature->title,
                'status' => $issue->feature->status->value,
                'statusLabel' => $issue->feature->status->label(),
                'votesCount' => $issue->feature->votes()->where('direction', 1)->count(),
                'downvotesCount' => $issue->feature->votes()->where('direction', -1)->count(),
                'authorName' => $issue->feature->author?->name,
                'createdAt' => $issue->feature->created_at->toISOString(),
            ] : null,
            'activity' => $request->user()->hasOrganizationPermission($currentOrganization, OrganizationPermission::ViewActivity)
                ? ActivitySummary::collection($issue->activitiesAsSubject()->with('causer')->latest('id')->limit(30)->get())
                : [],
            ...$this->formOptions($currentOrganization, $request->user(), $issue->team ?? $request->user()->currentTeamFor($currentOrganization)),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function update(SaveIssueRequest $request, Organization $currentOrganization, Issue $issue): RedirectResponse
    {
        Gate::authorize('update', $issue);

        $issue->update($request->safe()->except(['status', 'sprint_id', 'board_column_id']));

        if ($request->has('sprint_id')) {
            $issue->planInto($request->filled('sprint_id') ? Sprint::whereKey($request->integer('sprint_id'))->firstOrFail() : null);
        }

        if ($request->has('board_column_id')) {
            $issue->moveToColumn(BoardColumn::whereKey($request->integer('board_column_id'))->firstOrFail());
        }

        if ($request->has('status')) {
            $issue->changeStatus(IssueStatus::from($request->validated('status')));
        }

        return back();
    }

    public function destroy(Organization $currentOrganization, Issue $issue): RedirectResponse
    {
        Gate::authorize('delete', $issue);

        $issue->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue deleted.')]);

        return to_route('issues.index');
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function recentActivity(Collection $projectIds, ?Team $team): Collection
    {
        $issueIds = Issue::query()
            ->select('id')
            ->whereIn('project_id', $projectIds)
            ->when($team, fn (Builder $query, Team $team) => $query->where(fn (Builder $query) => $query->where('team_id', $team->id)->orWhereNull('team_id')));

        return Activity::query()
            ->where('log_name', 'issues')
            ->where('subject_type', (new Issue)->getMorphClass())
            ->whereIn('subject_id', $issueIds)
            ->with(['causer', 'subject.project'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (Activity $activity) {
                /** @var Issue $issue */
                $issue = $activity->subject;

                return [
                    ...(array) ActivitySummary::fromActivity($activity),
                    'issue' => [
                        'id' => $issue->id,
                        'reference' => $issue->reference(),
                        'title' => $issue->title,
                    ],
                ];
            });
    }

    /** @return array<string, mixed> */
    private function issueSummary(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'number' => $issue->number,
            'reference' => $issue->reference(),
            'title' => $issue->title,
            'status' => $issue->status->value,
            'statusLabel' => $issue->boardColumn->name ?? $issue->status->label(),
            'priority' => $issue->priority->value,
            'priorityLabel' => $issue->priority->label(),
            'points' => $issue->points,
            'project' => ['name' => $issue->project->name, 'slug' => $issue->project->slug],
            'teamName' => $issue->team?->name,
            'assigneeName' => $issue->assignee?->name,
            'hasFeature' => $issue->feature_id !== null,
            'createdAt' => $issue->created_at->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function formOptions(Organization $organization, User $user, ?Team $team = null): array
    {
        return [
            'projects' => $organization->projects()
                ->visibleTo($user)
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug]),
            'teams' => $organization->teams()
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team) => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug]),
            'sprints' => Sprint::query()
                ->when(
                    $team,
                    fn (Builder $query, Team $team) => $query->whereMorphedTo('owner', $team),
                    fn (Builder $query) => $query->where(fn (Builder $query) => $query
                        ->whereMorphedTo('owner', $organization)
                        ->orWhere(fn (Builder $query) => $query
                            ->where('owner_type', (new Team)->getMorphClass())
                            ->whereIn('owner_id', $organization->teams()->select('id')))),
                )
                ->where('status', '!=', SprintStatus::Completed)
                ->with(['owner', 'issues' => fn ($query) => $query->with(['project', 'assignee', 'sprint', 'team', 'boardColumn'])->orderBy('position')->orderBy('number')])
                ->withCount('issues')
                ->withSum('issues', 'points')
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Sprint $sprint) => [
                    'id' => $sprint->id,
                    'name' => $sprint->name,
                    'teamId' => $sprint->teamId(),
                    'teamName' => $sprint->owner->name,
                    'status' => $sprint->status->value,
                    'statusLabel' => $sprint->status->label(),
                    'startsAt' => $sprint->starts_at->toDateString(),
                    'endsAt' => $sprint->ends_at->toDateString(),
                    'issuesCount' => $sprint->issues_count,
                    'points' => (int) $sprint->issues_sum_points,
                    'issues' => $sprint->issues->map(fn (Issue $issue) => $this->issueSummary($issue)),
                ]),
            'members' => $organization->members()
                ->orderBy('name')
                ->get()
                ->map(fn (User $member) => ['id' => $member->id, 'name' => $member->name]),
            'statuses' => IssueStatus::options(),
            'priorities' => IssuePriority::options(),
        ];
    }
}
