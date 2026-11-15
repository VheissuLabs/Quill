<?php

namespace App\Enums;

enum OrganizationModule: string
{
    case Feedback = 'feedback';

    case Development = 'development';

    case Teams = 'teams';

    case Billing = 'billing';

    public function key(): string
    {
        return match ($this) {
            self::Feedback => 'feedback',
            self::Development => 'development',
            self::Teams => 'teams',
            self::Billing => 'billing',
        };
    }

    /** @return array<int, OrganizationCapability> */
    public function capabilities(): array
    {
        return match ($this) {
            self::Feedback => [
                OrganizationCapability::Boards,
                OrganizationCapability::Voting,
            ],
            self::Development => [
                OrganizationCapability::Issues,
                OrganizationCapability::Sprints,
                OrganizationCapability::Workflow,
            ],
            self::Teams => [],
            self::Billing => [
                OrganizationCapability::Invoices,
            ],
        };
    }
}
