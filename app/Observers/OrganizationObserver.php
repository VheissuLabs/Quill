<?php

namespace App\Observers;

use App\Actions\Organizations\SeedDefaultRoles;
use App\Models\Organization;

class OrganizationObserver
{
    public function __construct(protected SeedDefaultRoles $seedDefaultRoles) {}

    public function created(Organization $organization): void
    {
        $this->seedDefaultRoles->handle($organization);
    }
}
