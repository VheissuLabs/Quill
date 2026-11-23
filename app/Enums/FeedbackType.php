<?php

namespace App\Enums;

enum FeedbackType: string
{
    case Feature = 'feature';
    case Bug = 'bug';

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'Feature request',
            self::Bug => 'Bug report',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Feature => 'Feature',
            self::Bug => 'Bug',
        };
    }

    /** What the vote button offers, which is not the same question for each. */
    public function voteLabel(): string
    {
        return match ($this) {
            self::Feature => 'For',
            self::Bug => "I've experienced this",
        };
    }

    public function acceptsOpposingVotes(): bool
    {
        return $this === self::Feature;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases()
        );
    }
}
