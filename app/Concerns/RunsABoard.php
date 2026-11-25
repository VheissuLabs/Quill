<?php

namespace App\Concerns;

use App\Enums\IssueStatus;
use App\Models\BoardColumn;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

trait RunsABoard
{
    /** @return MorphMany<BoardColumn, $this> */
    public function columns(): MorphMany
    {
        return $this->morphMany(BoardColumn::class, 'owner')->orderBy('position');
    }

    /** @return MorphMany<Sprint, $this> */
    public function sprints(): MorphMany
    {
        return $this->morphMany(Sprint::class, 'owner');
    }

    /** @param  array<string, mixed>  $attributes */
    public function addColumn(array $attributes): BoardColumn
    {
        return $this->columns()->create([
            ...$attributes,
            'position' => ($this->columns()->max('position') ?? -1) + 1,
        ]);
    }

    /** @param  array<int, int>  $columnIds */
    public function reorderColumns(array $columnIds): void
    {
        DB::transaction(function () use ($columnIds) {
            foreach (array_values($columnIds) as $position => $columnId) {
                $this->columns()->whereKey($columnId)->update(['position' => $position]);
            }
        });
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
