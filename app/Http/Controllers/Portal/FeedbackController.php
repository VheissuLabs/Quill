<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Comments\FeedbackCommentCommands;
use App\Enums\FeedbackStatus;
use App\Events\FeedbackChanged;
use App\Events\FeedbackCommented;
use App\Events\FeedbackVoted;
use App\Events\IssuesChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\FeedbackPageRequest;
use App\Http\Requests\Feedback\FeedbackRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\FeedbackResource;
use App\Http\Resources\FeedbackViewerResource;
use App\Http\Resources\PortalOrganizationResource;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function show(FeedbackPageRequest $request, Organization $organization, Project $project, Feedback $feedback): Response|RedirectResponse
    {
        $user = auth()->user();

        $isMember = $user?->belongsToOrganization($organization) ?? false;
        $viewer = FeedbackViewerResource::make($feedback)->for($user)->resolve();

        $feedback = Feedback::query()
            ->whereKey($feedback->id)
            ->withSummary($user)
            ->firstOrFail();

        $newestFirst = $request->newestFirst();

        return Inertia::render('portal/Feedback', [
            'organization' => PortalOrganizationResource::make($organization),
            'project' => ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug],
            'feedback' => FeedbackChanged::live($summary = FeedbackResource::make($feedback)->withIssue($isMember)->resolve(), $project->id, FeedbackVoted::class, FeedbackCommented::class),
            'issue' => $isMember ? IssuesChanged::live($summary['issue'], $organization->id) : $summary['issue'],
            'comments' => FeedbackCommented::live(fn () => CommentResource::window($feedback->comments(), $user, $isMember, $newestFirst), $project->id),
            'commentOrder' => $newestFirst ? 'newest' : 'oldest',
            'activity' => FeedbackChanged::live($viewer['canViewActivity']
                ? ActivityResource::collection($feedback->activitiesAsSubject()->with('causer')->latest('id')->limit(20)->get())
                : [], $project->id),
            'commands' => app(FeedbackCommentCommands::class)->availableTo($user, $feedback),
            'mentionableUsers' => $viewer['canComment']
                ? $feedback->mentionableUsers()->map(fn (User $mentionable) => ['id' => $mentionable->id, 'name' => $mentionable->name])->values()
                : [],
            'statuses' => FeedbackStatus::options(),
            'viewer' => $viewer,
        ]);
    }

    public function store(FeedbackRequest $request, Organization $organization, Project $project): RedirectResponse
    {
        $feedback = Feedback::submit($project, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feedback requested.')]);

        return to_route('portal.feedback.show', [$organization, $project, $feedback]);
    }

    public function update(FeedbackRequest $request, Organization $organization, Project $project, Feedback $feedback): RedirectResponse
    {
        $feedback->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feedback updated.')]);

        return back();
    }

    public function destroy(Organization $organization, Project $project, Feedback $feedback): RedirectResponse
    {
        $feedback->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feedback deleted.')]);

        return to_route('portal.projects.show', [$organization, $project]);
    }
}
