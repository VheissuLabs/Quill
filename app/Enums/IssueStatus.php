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

    public function isClosed(): bool
    {
        return in_array($this, [self::Done, self::Cancelled], true);
    }

    public function featureStatus(): ?FeatureStatus
    {
        return match ($this) {
            self::Backlog, self::Todo => FeatureStatus::Planned,
            self::InProgress => FeatureStatus::InProgress,
            self::Done => FeatureStatus::Completed,
            self::Cancelled => null,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function columnStatuses(): array
    {
        return [self::Todo, self::InProgress, self::Done];
    }

    /**
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()])
            ->toArray();
    }
}
