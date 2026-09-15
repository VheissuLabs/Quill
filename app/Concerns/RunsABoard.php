<?php

namespace App\Concerns;

use App\Enums\IssueStatus;
use App\Models\BoardColumn;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared by the two things that can run a board: a team, and an organization that has no teams.
 *
 * Teams are opt in. Until someone creates one the organization owns the board and the sprints,
 * so a small company can plan work without setting up departments first.
 */
trait RunsABoard
{
    /**
     * @return MorphMany<BoardColumn, $this>
     */
    public function columns(): MorphMany
    {
        return $this->morphMany(BoardColumn::class, 'owner')->orderBy('position');
    }

    /**
     * @return MorphMany<Sprint, $this>
     */
    public function sprints(): MorphMany
    {
        return $this->morphMany(Sprint::class, 'owner');
    }

    public function createDefaultColumns(): void
    {
        foreach (IssueStatus::columnStatuses() as $position => $status) {
            $this->columns()->create([
                'name' => $status->label(),
                'status' => $status,
                'position' => $position,
            ]);
        }
    }
}
