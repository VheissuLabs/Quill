<?php

namespace App\Enums;

enum IssueLinkType: string
{
    case Parent = 'parent';
    case Blocks = 'blocks';
    case Relates = 'relates';
    case Duplicates = 'duplicates';
    case Clones = 'clones';

    public function label(): string
    {
        return match ($this) {
            self::Parent => 'Parent of',
            self::Blocks => 'Blocks',
            self::Relates => 'Relates to',
            self::Duplicates => 'Duplicates',
            self::Clones => 'Clones',
        };
    }

    public function inverseLabel(): string
    {
        return match ($this) {
            self::Parent => 'Child of',
            self::Blocks => 'Blocked by',
            self::Relates => 'Relates to',
            self::Duplicates => 'Duplicated by',
            self::Clones => 'Cloned by',
        };
    }

    public function isSymmetric(): bool
    {
        return $this === self::Relates;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()])
            ->all();
    }

    /**
     * Both readings of every type, as offered to someone looking at one end of a link.
     * A symmetric type reads the same either way, so it appears once.
     *
     * @return array<int, array{value: string, inverse: bool, label: string}>
     */
    public static function perspectives(): array
    {
        return collect(self::cases())
            ->flatMap(fn (self $type) => $type->isSymmetric()
                ? [['value' => $type->value, 'inverse' => false, 'label' => $type->label()]]
                : [
                    ['value' => $type->value, 'inverse' => false, 'label' => $type->label()],
                    ['value' => $type->value, 'inverse' => true, 'label' => $type->inverseLabel()],
                ])
            ->all();
    }
}
