<?php

namespace App\Http\Resources;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Issue */
class IssueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'reference' => $this->reference(),
            'title' => $this->title,
            'status' => $this->status->value,
            'statusLabel' => $this->boardColumn->name ?? $this->status->label(),
            'boardColumnId' => $this->board_column_id,
            'priority' => $this->priority->value,
            'priorityLabel' => $this->priority->label(),
            'points' => $this->points,
            'project' => ['name' => $this->project->name, 'slug' => $this->project->slug],
            'teamName' => $this->team?->name,
            'assigneeId' => $this->assignee_id,
            'assigneeName' => $this->assignee?->name,
            'hasFeature' => $this->feature_id !== null,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
