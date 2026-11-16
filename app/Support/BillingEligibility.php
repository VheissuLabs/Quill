<?php

namespace App\Support;

use App\Enums\OrganizationCapability;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

class BillingEligibility
{
    /** @var array<int, bool> */
    private array $eligible = [];

    public function isEligible(Organization $organization): bool
    {
        return $this->eligible[$organization->id] ??= $organization->canUse(OrganizationCapability::BillingCycles);
    }

    /** @return Collection<int, User> */
    public function for(Organization $organization): Collection
    {
        return $organization->members()->get()
            ->filter(fn (User $user): bool => $organization->allowsInvoiceManagementBy($user))
            ->values();
    }
}
