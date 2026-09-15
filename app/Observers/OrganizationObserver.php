<?php

namespace App\Observers;

use App\Models\Organization;

class OrganizationObserver
{
    /**
     * Give a new organization a slug when none was supplied.
     */
    public function creating(Organization $organization): void
    {
        if (empty($organization->slug)) {
            $organization->slug = Organization::generateUniqueOrganizationSlug($organization->name);
        }
    }

    /**
     * Keep the slug in step with a renamed organization.
     */
    public function updating(Organization $organization): void
    {
        if ($organization->isDirty('name')) {
            $organization->slug = Organization::generateUniqueOrganizationSlug($organization->name, $organization->id);
        }
    }

    /**
     * Every organization starts with the locked owner role and nothing else.
     */
    public function created(Organization $organization): void
    {
        $organization->createOwnerRole();
    }
}
