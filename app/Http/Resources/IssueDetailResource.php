<?php

namespace App\Http\Resources;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\Request;

/** @mixin Issue */
class IssueDetailResource extends IssueResource
{
    public function __construct(Issue $resource, private User $viewer)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'teamId' => $this->team_id,
            'sprintId' => $this->sprint_id,
            'assigneeId' => $this->assignee_id,
            'creatorName' => $this->creator?->name,
            'completedAt' => $this->completed_at?->toISOString(),
            'columns' => BoardColumnResource::collection(
                ($this->team ?? $this->viewer->boardOwnerFor($this->project->organization))->columns
            ),
        ];
    }
}
