<?php

namespace App\Enums;

enum OrganizationCapability: string
{
    case Boards = 'boards';
    case Voting = 'voting';
    case Issues = 'issues';
    case Sprints = 'sprints';
    case Workflow = 'workflow';
    case Invoices = 'invoices';

    public function module(): OrganizationModule
    {
        return match ($this) {
            self::Boards, self::Voting => OrganizationModule::Feedback,
            self::Issues, self::Sprints, self::Workflow => OrganizationModule::Development,
            self::Invoices => OrganizationModule::Billing,
        };
    }
}
