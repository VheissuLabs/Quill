<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Comments\FeatureCommentCommands;
use App\Enums\FeatureStatus;
use App\Enums\VoteDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Features\FeatureRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\FeatureResource;
use App\Http\Resources\FeatureViewerResource;
use App\Http\Resources\PortalOrganizationResource;
use App\Models\Comment;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FeatureController extends Controller
{
    public function show(Organization $organization, Project $project, Feature $feature): Response|RedirectResponse
    {
        $user = auth()->user();

        $isMember = $user?->belongsToOrganization($organization) ?? false;
        $viewer = FeatureViewerResource::make($feature)->for($user)->resolve();

        $feature = Feature::query()
            ->whereKey($feature->id)
            ->withVoteSummary($user)
            ->withCount(['comments' => fn ($query) => $query->public()])
            ->with(['author', 'issue.project'])
            ->firstOrFail();

        return Inertia::render('portal/Feature', [
            'organization' => PortalOrganizationResource::make($organization),
            'project' => ['name' => $project->name, 'slug' => $project->slug],
            'feature' => $summary = FeatureResource::make($feature)->withIssue($isMember)->resolve(),
            'issue' => $summary['issue'],
            'comments' => $feature->comments()->threadsVisibleTo($isMember)->get()
                ->map(fn (Comment $comment) => CommentResource::make($comment, $user)->resolve()),
            'activity' => $viewer['canViewActivity']
                ? ActivityResource::collection($feature->activitiesAsSubject()->with('causer')->latest('id')->limit(20)->get())
                : [],
            'commands' => app(FeatureCommentCommands::class)->availableTo($user, $feature),
            'mentionableUsers' => $viewer['canComment']
                ? $feature->mentionableUsers()->map(fn (User $mentionable) => ['id' => $mentionable->id, 'name' => $mentionable->name])->values()
                : [],
            'statuses' => FeatureStatus::options(),
            'viewer' => $viewer,
        ]);
    }

    public function store(FeatureRequest $request, Organization $organization, Project $project): RedirectResponse
    {
        $feature = $project->features()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => FeatureStatus::Open,
        ]);

        $feature->castVote($request->user(), VoteDirection::Up);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature requested.')]);

        return to_route('portal.features.show', [$organization, $project, $feature]);
    }

    public function update(FeatureRequest $request, Organization $organization, Project $project, Feature $feature): RedirectResponse
    {
        $feature->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature updated.')]);

        return back();
    }

    public function destroy(Organization $organization, Project $project, Feature $feature): RedirectResponse
    {
        $feature->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature deleted.')]);

        return to_route('portal.projects.show', [$organization, $project]);
    }
}
