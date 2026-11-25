<?php

namespace App\Http\Resources;

use App\Enums\FeedbackStatus;
use App\Enums\OrganizationCapability;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin Organization */
class DashboardResource extends JsonResource
{
    public function __construct(Organization $resource, private User $viewer)
    {
        parent::__construct($resource);
    }

    /** @return Collection<int, int> */
    public function visibleProjectIds(): Collection
    {
        return $this->resource->canUse(OrganizationCapability::Boards)
            ? $this->resource->projects()->visibleTo($this->viewer)->pluck('projects.id')
            : collect();
    }

    /** @return array{projects: array<int|string, mixed>, recentFeedback: array<int|string, mixed>, stats: array{projectsCount: int, openFeedbackCount: mixed, votesCount: int}} */
    public function toArray(Request $request): array
    {
        $projectIds = $this->visibleProjectIds();

        $projects = DashboardProjectResource::collection($this->resource->projects()
            ->whereKey($projectIds)
            ->with('client')
            ->withCount(['feedback', 'feedback as open_features_count' => fn ($query) => $query->where('status', FeedbackStatus::Open)])
            ->orderBy('name')
            ->get())->resolve();

        return [
            'projects' => $projects,
            'recentFeedback' => RecentFeedbackResource::collection(Feedback::whereIn('project_id', $projectIds)
                ->active()
                ->with('project')
                ->withCount(['votes' => fn ($query) => $query->where('direction', 1)])
                ->latest()
                ->orderByDesc('id')
                ->limit(8)
                ->get())->resolve(),
            'stats' => [
                'projectsCount' => count($projects),
                'openFeedbackCount' => collect($projects)->sum('openFeedbackCount'),
                'votesCount' => Vote::where('direction', 1)->whereHas('feedback', fn ($query) => $query->whereIn('project_id', $projectIds))->count(),
            ],
        ];
    }
}
