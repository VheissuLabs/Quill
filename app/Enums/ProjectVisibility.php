<?php

namespace App\Enums;

enum ProjectVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $visibility) => ['value' => $visibility->value, 'label' => $visibility->label()])
            ->toArray();
    }
}
