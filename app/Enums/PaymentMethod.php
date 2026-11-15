<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Check = 'check';
    case Wire = 'wire';
    case Ach = 'ach';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ach => 'ACH',
            default => ucfirst($this->value),
        };
    }
}
