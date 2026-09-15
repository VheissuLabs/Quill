<?php

namespace App\Enums;

enum OrganizationCapability: string
{
    case Boards = 'boards';
    case Voting = 'voting';
    case Issues = 'issues';
    case Sprints = 'sprints';
    case Workflow = 'workflow';

    /**
     * Get the module that owns this capability.
     */
    public function module(): OrganizationModule
    {
        return match ($this) {
            self::Boards, self::Voting => OrganizationModule::FeatureVoting,
            self::Issues, self::Sprints, self::Workflow => OrganizationModule::Development,
        };
    }
}
