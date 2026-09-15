<?php

namespace App\Enums;

enum OrganizationModule: string
{
    case FeatureVoting = 'feature_voting';

    case Development = 'development';

    /**
     * Get the frontend key for this module.
     */
    public function key(): string
    {
        return match ($this) {
            self::FeatureVoting => 'featureVoting',
            self::Development => 'development',
        };
    }

    /**
     * Get the capabilities that belong to this module.
     *
     * @return array<int, OrganizationCapability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::FeatureVoting => [
                OrganizationCapability::Boards,
                OrganizationCapability::Voting,
            ],
            self::Development => [
                OrganizationCapability::Issues,
                OrganizationCapability::Sprints,
                OrganizationCapability::Workflow,
            ],
        };
    }
}
