<?php

namespace App\Http\Resources;

use App\Models\BoardColumn;
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
            'statusLabel' => $this->columnName(),
            'boardColumnId' => $this->board_column_id,
            'priority' => $this->priority->value,
            'priorityLabel' => $this->priority->label(),
            'points' => $this->points,
            'project' => ['name' => $this->project->name, 'slug' => $this->project->slug],
            'teamId' => $this->team_id,
            'teamName' => $this->team?->name,
            'assigneeId' => $this->assignee_id,
            'assigneeName' => $this->assignee?->name,
            'hasFeature' => $this->feature_id !== null,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }

    /**
     * Teams name their own columns, so a row says what its board calls the stage rather than
     * the generic status. Backlog and cancelled have no column and keep the plain label.
     */
    private function columnName(): string
    {
        if ($this->boardColumn) {
            return $this->boardColumn->name;
        }

        $column = $this->team?->columns->first(
            fn (BoardColumn $column) => $column->status === $this->status
        );

        return $column instanceof BoardColumn
            ? $column->name
            : $this->status->label();
    }
}
