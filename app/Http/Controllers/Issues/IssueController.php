<?php

namespace App\Http\Controllers\Issues;

use App\Actions\Comments\IssueCommentCommands;
use App\Actions\Issues\CreateIssue;
use App\Actions\Issues\UpdateIssue;
use App\Data\IssueChangesData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\IssueIndexRequest;
use App\Http\Requests\Issues\IssueRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\FeatureResource;
use App\Http\Resources\IssueActivityResource;
use App\Http\Resources\IssueDetailResource;
use App\Http\Resources\IssueFormResource;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function index(IssueIndexRequest $request, Organization $currentOrganization): Response
    {
        $user = $request->user();
        $team = $user->currentTeamFor($currentOrganization);
        $projectIds = $currentOrganization->projects()->visibleTo($user)->pluck('projects.id');
        $filters = $request->filters();
        $permissions = $user->toOrganizationPermissions($currentOrganization);

        return Inertia::render('issues/Index', [
            'issues' => IssueResource::collection(Issue::onOrganizationBoard($projectIds, $user, $team, $filters)->get()),
            'filters' => $filters,
            'activity' => $permissions['canViewActivity']
                ? IssueActivityResource::collection(Issue::recentActivity($projectIds, $team))
                : [],
            ...IssueFormResource::make($currentOrganization, $user, $team)->resolve(),
        ]);
    }

    public function store(IssueRequest $request, Organization $currentOrganization, CreateIssue $createIssue): RedirectResponse
    {
        $project = $currentOrganization->projects()->whereKey($request->integer('project_id'))->firstOrFail();

        $issue = $createIssue->handle($project, $request->issueAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':reference created.', ['reference' => $issue->reference()])]);

        return to_route('issues.show', ['issue' => $issue]);
    }

    public function show(Organization $currentOrganization, Issue $issue): Response
    {
        $user = auth()->user();
        $permissions = $user->toOrganizationPermissions($currentOrganization);

        $issue->load(['project.organization', 'team.columns', 'sprint', 'boardColumn.owner', 'assignee', 'creator',
            'feature' => fn ($query) => $query->withVoteSummary($user)->withCount('comments')->with('author')]);

        return Inertia::render('issues/Show', [
            'issue' => IssueDetailResource::make($issue, $user)->resolve(),
            'comments' => $issue->comments()->threadsVisibleTo(true)->get()
                ->map(fn ($comment) => CommentResource::make($comment, $user)->resolve()),
            'commands' => app(IssueCommentCommands::class)->availableTo($user, $issue),
            'mentionables' => $issue->mentionableUsers()->map(fn ($member) => ['id' => $member->id, 'name' => $member->name]),
            'feature' => $issue->feature ? FeatureResource::make($issue->feature)->resolve() : null,
            'activity' => $permissions['canViewActivity']
                ? ActivityResource::collection($issue->activitiesAsSubject()->with('causer')->latest('id')->limit(30)->get())
                : [],
            ...IssueFormResource::make($currentOrganization, $user, $issue->team ?? $user->currentTeamFor($currentOrganization))->resolve(),
        ]);
    }

    public function update(IssueRequest $request, Organization $currentOrganization, Issue $issue, UpdateIssue $updateIssue): RedirectResponse
    {
        $updateIssue->handle($issue, IssueChangesData::from($request->safe()));

        return back();
    }

    public function destroy(Organization $currentOrganization, Issue $issue): RedirectResponse
    {
        $issue->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue deleted.')]);

        return to_route('issues.index');
    }
}
