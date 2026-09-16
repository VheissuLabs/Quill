<?php

namespace App\Http\Controllers\Issues;

use App\Actions\Issues\CreateIssue;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\IssueRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\IssueActivityResource;
use App\Http\Resources\IssueFormResource;
use App\Http\Resources\IssueResource;
use App\Models\BoardColumn;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class IssueController extends Controller
{
    public function index(Request $request, Organization $currentOrganization): Response
    {
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
            ->ownedBy($team)
            ->whereNull('sprint_id')
            ->when($filters['assignee'] === 'me', fn (Builder $query) => $query->where('assignee_id', $user->id))
            ->orderBy('position')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->latest()
            ->get();

        return Inertia::render('issues/Index', [
            'issues' => IssueResource::collection($issues),
            'filters' => $filters,
            'activity' => $user->hasOrganizationPermission($currentOrganization, OrganizationPermission::ViewActivity)
                ? IssueActivityResource::collection(
                    Activity::query()
                        ->where('log_name', 'issues')
                        ->where('subject_type', (new Issue)->getMorphClass())
                        ->whereIn('subject_id', Issue::whereIn('project_id', $projectIds)->ownedBy($team)->select('id'))
                        ->with(['causer', 'subject.project'])
                        ->latest('id')
                        ->limit(20)
                        ->get()
                )
                : [],
            ...IssueFormResource::make($currentOrganization, $user, $team)->resolve(),
            'permissions' => $user->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function store(IssueRequest $request, Organization $currentOrganization, CreateIssue $createIssue): RedirectResponse
    {
        $project = $currentOrganization->projects()->whereKey($request->integer('project_id'))->firstOrFail();

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
        $issue->load(['project', 'team.columns', 'sprint', 'boardColumn.owner', 'assignee', 'creator', 'feature.author']);

        return Inertia::render('issues/Show', [
            'issue' => [
                ...IssueResource::make($issue)->resolve(),
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
                ? ActivityResource::collection($issue->activitiesAsSubject()->with('causer')->latest('id')->limit(30)->get())
                : [],
            ...IssueFormResource::make($currentOrganization, $request->user(), $issue->team ?? $request->user()->currentTeamFor($currentOrganization))->resolve(),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function update(IssueRequest $request, Organization $currentOrganization, Issue $issue): RedirectResponse
    {
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
        $issue->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue deleted.')]);

        return to_route('issues.index');
    }
}
