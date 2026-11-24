<?php

namespace App\Enums;

enum SidebarSection: string
{
    case Feedback = 'feedback';
    case Development = 'development';
    case Support = 'support';
    case Billing = 'billing';

    /** @return array<int, string> */
    public static function defaultOrder(): array
    {
        return array_column(self::cases(), 'value');
    }
}
