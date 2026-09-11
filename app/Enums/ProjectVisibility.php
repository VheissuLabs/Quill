<?php

namespace App\Enums;

enum ProjectVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    /**
     * Get the display label for the visibility.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get the visibilities as options for a select input.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $visibility) => ['value' => $visibility->value, 'label' => $visibility->label()])
            ->toArray();
    }
}
