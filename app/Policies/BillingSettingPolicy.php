<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;

class BillingSettingPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Invoices)
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ManageInvoices);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->viewAny($user, $organization);
    }
}
