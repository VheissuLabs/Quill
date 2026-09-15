<?php

namespace App\Observers;

use App\Models\Organization;
use Illuminate\Support\Facades\Schema;

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
     * Every organization starts with the locked owner role and a board of its own. Teams are opt
     * in, so until someone creates one the organization runs the board itself.
     */
    public function created(Organization $organization): void
    {
        $organization->createOwnerRole();

        // Migrations that predate the board create organizations too, and run before the table
        // they would write to exists.
        if (Schema::hasTable('board_columns')) {
            $organization->createDefaultColumns();
        }
    }
}
