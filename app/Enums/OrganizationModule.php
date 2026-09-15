<?php

namespace App\Enums;

enum OrganizationModule: string
{
    case FeatureVoting = 'feature_voting';

    case Development = 'development';

    case Teams = 'teams';

    public function key(): string
    {
        return match ($this) {
            self::FeatureVoting => 'featureVoting',
            self::Development => 'development',
            self::Teams => 'teams',
        };
    }

    /**
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
            self::Teams => [],
        };
    }
}
