<?php

namespace App\Enums;

enum SquareCheckoutStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
