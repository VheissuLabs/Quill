<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case AwaitingCustomer = 'awaiting_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::AwaitingCustomer => 'Awaiting customer',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::AwaitingCustomer], true);
    }

    /** @return array<array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()])
            ->toArray();
    }
}
