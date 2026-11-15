<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkType;

class WorkTypePolicy
{
    public function create(User $user, Organization $organization): bool
    {
        return $this->manages($user, $organization);
    }

    public function update(User $user, WorkType $workType): bool
    {
        return $this->manages($user, $workType->organization);
    }

    public function delete(User $user, WorkType $workType): bool
    {
        return $this->manages($user, $workType->organization);
    }

    private function manages(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Invoices)
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ManageInvoices);
    }
}
