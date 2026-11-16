<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Check = 'check';
    case Wire = 'wire';
    case Ach = 'ach';
    case Cash = 'cash';
    case Zelle = 'zelle';
    case CashApp = 'cash_app';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ach => 'ACH',
            self::CashApp => 'Cash App',
            default => ucfirst($this->value),
        };
    }
}
