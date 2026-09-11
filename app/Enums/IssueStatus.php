<?php

namespace App\Enums;

/**
 * The stage an issue is in. Teams name their own board columns; each column maps to one of the
 * column statuses (Todo, In progress, Done) so the rest of the app can reason about progress.
 */
enum IssueStatus: string
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Todo => 'Todo',
            self::InProgress => 'In progress',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Determine if work on the issue has finished, one way or the other.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Done, self::Cancelled], true);
    }

    /**
     * The statuses a board column can map to.
     *
     * @return array<int, self>
     */
    public static function columnStatuses(): array
    {
        return [self::Todo, self::InProgress, self::Done];
    }

    /**
     * Get the statuses as options for a select input.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()])
            ->toArray();
    }
}
