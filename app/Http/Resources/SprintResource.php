<?php

namespace App\Http\Resources;

use App\Models\Sprint;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sprint */
class SprintResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'startsAt' => $this->starts_at->toDateString(),
            'endsAt' => $this->ends_at->toDateString(),
            'teamId' => $this->teamId(),
            'teamName' => $this->owner->name,
            'owner' => [
                'name' => $this->owner->name,
                'slug' => $this->owner->slug,
                'isTeam' => $this->owner instanceof Team,
            ],
            'issuesCount' => (int) $this->issues_count,
            'doneIssuesCount' => $this->whenCounted('done_issues'),
            'points' => (int) $this->issues_sum_points,
            'issues' => IssueResource::collection($this->whenLoaded('issues')),
        ];
    }
}
