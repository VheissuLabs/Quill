<?php

namespace App\Enums;

enum AddressType: string
{
    case Billing = 'billing';
    case Mailing = 'mailing';
    case Office = 'office';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
