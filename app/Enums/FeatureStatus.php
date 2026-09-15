<?php

namespace App\Enums;

enum FeatureStatus: string
{
    case Open = 'open';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Planned => 'Planned',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Declined => 'Declined',
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Completed, self::Declined], true);
    }

    /** @return array<int, self> */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => ! $status->isSettled()));
    }

    /** @return array<array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()])
            ->toArray();
    }
}
