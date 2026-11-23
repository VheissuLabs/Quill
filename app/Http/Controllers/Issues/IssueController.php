<?php

namespace App\Http\Controllers\Issues;

use App\Actions\Comments\IssueCommentCommands;
use App\Actions\Issues\CreateIssue;
use App\Actions\Issues\UpdateIssue;
use App\Data\IssueChangesData;
use App\Enums\IssueLinkType;
use App\Events\IssuesChanged;
use App\Events\MembersChanged;
use App\Events\ProjectsChanged;
use App\Events\TeamsChanged;
use App\Events\TicketsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\IssueIndexRequest;
use App\Http\Requests\Issues\IssueRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\FeedbackResource;
use App\Http\Resources\IssueActivityResource;
use App\Http\Resources\IssueDetailResource;
use App\Http\Resources\IssueFormResource;
use App\Http\Resources\IssueLinkResource;
use App\Http\Resources\IssueResource;
use App\Http\Resources\LinkedTicketResource;
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
            ...IssueFormResource::make($currentOrganization, $user, $team)->liveProps(),
            'issues' => IssuesChanged::live(fn () => IssueResource::collection(Issue::onOrganizationBoard($projectIds, $user, $team, $filters)->get()), $currentOrganization->getKey()),
            'filters' => $filters,
            'activity' => IssuesChanged::live(fn () => $permissions['canViewActivity'] ? IssueActivityResource::collection(Issue::recentActivity($projectIds, $team)) : [], $currentOrganization->getKey()),
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
            'feedback' => fn ($query) => $query->withVoteSummary($user)->withCount('comments')->with('author')]);

        return Inertia::render('issues/Show', [
            'issue' => IssuesChanged::live(fn () => IssueDetailResource::make($issue, $user)->resolve(), $currentOrganization->getKey()),
            'links' => IssuesChanged::live(fn () => IssueLinkResource::collection($issue->relatedIssues()), $currentOrganization->getKey()),
            'linkTypes' => IssueLinkType::perspectives(),
            'comments' => Inertia::scroll(fn () => CommentResource::page($issue->comments(), $user, true)),
            'commands' => app(IssueCommentCommands::class)->availableTo($user, $issue),
            'mentionables' => MembersChanged::live($issue->mentionableUsers()->map(fn ($member) => ['id' => $member->id, 'name' => $member->name]), $currentOrganization->getKey(), TeamsChanged::class, ProjectsChanged::class),
            'feedback' => FeedbackResource::liveForIssue($issue),
            'tickets' => TicketsChanged::live(fn () => LinkedTicketResource::forIssue($issue, $user), $currentOrganization->getKey(), IssuesChanged::class),
            'activity' => IssuesChanged::live(fn () => $permissions['canViewActivity'] ? ActivityResource::collection($issue->activitiesAsSubject()->with('causer')->latest('id')->limit(30)->get()) : [], $currentOrganization->getKey()),
            ...IssueFormResource::make($currentOrganization, $user, $issue->team ?? $user->currentTeamFor($currentOrganization))->liveProps(),
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
