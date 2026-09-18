<?php

namespace App\Data;

use App\Enums\IssueStatus;
use App\Models\BoardColumn;
use App\Models\Sprint;
use Illuminate\Support\ValidatedInput;
use Spatie\LaravelData\Data;

class IssueChangesData extends Data
{
    /** @param  array<string, mixed>  $attributes */
    public function __construct(
        public array $attributes,
        public bool $movesIssue,
        public Sprint|BoardColumn|IssueStatus|null $destination,
    ) {}

    public static function fromValidatedInput(ValidatedInput $input): self
    {
        $columnId = $input->integer('board_column_id');
        $sprintId = $input->integer('sprint_id');

        $destination = match (true) {
            $input->has('board_column_id') => BoardColumn::findOrFail($columnId),
            $input->has('status') => $input->enum('status', IssueStatus::class),
            $sprintId !== 0 => Sprint::findOrFail($sprintId),
            default => null,
        };

        return new self(
            $input->except(['status', 'sprint_id', 'board_column_id']),
            $input->hasAny(['sprint_id', 'board_column_id', 'status']),
            $destination,
        );
    }
}
