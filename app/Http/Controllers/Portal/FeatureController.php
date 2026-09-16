<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Comments\RunCommentCommands;
use App\Enums\FeatureStatus;
use App\Enums\OrganizationPermission;
use App\Enums\VoteDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Features\FeatureRequest;
use App\Http\Resources\ActivitySummary;
use App\Http\Resources\CommentSummary;
use App\Http\Resources\FeatureResource;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FeatureController extends Controller
{
    public function show(Request $request, Organization $organization, Project $project, Feature $feature): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user && ! $project->isPublic()) {
            return redirect()->guest(route('login'));
        }

        Gate::authorize('view', $feature);

        $isMember = $user?->belongsToOrganization($organization) ?? false;
        $canViewActivity = $user?->hasOrganizationPermission($organization, OrganizationPermission::ViewActivity) ?? false;

        $feature = Feature::query()
            ->whereKey($feature->id)
            ->withVoteSummary($user)
            ->withCount(['comments' => fn ($query) => $query->public()])
            ->with(['author', 'issue.project'])
            ->firstOrFail();

        return Inertia::render('portal/Feature', [
            'organization' => ['name' => $organization->name, 'slug' => $organization->slug, 'logoUrl' => $organization->logoUrl()],
            'project' => ['name' => $project->name, 'slug' => $project->slug],
            'feature' => $summary = FeatureResource::make($feature, $isMember)->resolve(),
            'issue' => $summary['issue'],
            'comments' => CommentSummary::threads(
                $feature->comments()
                    ->topLevel()
                    ->with([
                        'author',
                        'mentions',
                        'replies' => fn ($query) => $query->with(['author', 'mentions'])->when(! $isMember, fn ($query) => $query->public()),
                    ])
                    ->when(! $isMember, fn ($query) => $query->public())
                    ->oldest()
                    ->get(),
                $user,
            ),
            'activity' => $canViewActivity
                ? ActivitySummary::collection($feature->activitiesAsSubject()->with('causer')->latest('id')->limit(20)->get())
                : [],
            'commands' => RunCommentCommands::availableTo($user, $feature),
            'mentionableUsers' => $user?->can('comment', $feature)
                ? $feature->mentionableUsers()->map(fn (User $mentionable) => ['id' => $mentionable->id, 'name' => $mentionable->name])->values()
                : [],
            'statuses' => FeatureStatus::options(),
            'viewer' => [
                'isMember' => $isMember,
                'canUpdate' => $user?->can('update', $feature) ?? false,
                'canUpdateStatus' => $user?->can('updateStatus', $feature) ?? false,
                'canDelete' => $user?->can('delete', $feature) ?? false,
                'canVote' => $user?->can('vote', $feature) ?? false,
                'canComment' => $user?->can('comment', $feature) ?? false,
                'canCommentInternally' => $user?->can('commentInternally', $feature) ?? false,
                'canConvert' => $user?->can('convert', $feature) ?? false,
                'canViewActivity' => $canViewActivity,
            ],
        ]);
    }

    public function store(FeatureRequest $request, Organization $organization, Project $project): RedirectResponse
    {
        Gate::authorize('create', [Feature::class, $project]);

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
        Gate::authorize('update', $feature);

        $feature->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature updated.')]);

        return back();
    }

    public function destroy(Organization $organization, Project $project, Feature $feature): RedirectResponse
    {
        Gate::authorize('delete', $feature);

        $feature->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature deleted.')]);

        return to_route('portal.projects.show', [$organization, $project]);
    }
}
