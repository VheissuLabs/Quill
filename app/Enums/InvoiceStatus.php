<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
