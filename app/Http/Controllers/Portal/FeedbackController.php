<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Comments\FeedbackCommentCommands;
use App\Enums\FeedbackStatus;
use App\Enums\VoteDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\FeedbackRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\FeedbackResource;
use App\Http\Resources\FeedbackViewerResource;
use App\Http\Resources\PortalOrganizationResource;
use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function show(Organization $organization, Project $project, Feedback $feedback): Response|RedirectResponse
    {
        $user = auth()->user();

        $isMember = $user?->belongsToOrganization($organization) ?? false;
        $viewer = FeedbackViewerResource::make($feedback)->for($user)->resolve();

        $feedback = Feedback::query()
            ->whereKey($feedback->id)
            ->withVoteSummary($user)
            ->withCount(['comments' => fn ($query) => $query->public()])
            ->with(['author', 'issue.project'])
            ->firstOrFail();

        return Inertia::render('portal/Feedback', [
            'organization' => PortalOrganizationResource::make($organization),
            'project' => ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug],
            'feedback' => $summary = FeedbackResource::make($feedback)->withIssue($isMember)->resolve(),
            'issue' => $summary['issue'],
            'comments' => $feedback->comments()->threadsVisibleTo($isMember)->get()
                ->map(fn (Comment $comment) => CommentResource::make($comment, $user)->resolve()),
            'activity' => $viewer['canViewActivity']
                ? ActivityResource::collection($feedback->activitiesAsSubject()->with('causer')->latest('id')->limit(20)->get())
                : [],
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
        $feedback = $project->feedback()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => FeedbackStatus::Open,
        ]);

        $feedback->castVote($request->user(), VoteDirection::Up);

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
