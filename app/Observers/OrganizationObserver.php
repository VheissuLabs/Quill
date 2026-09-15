<?php

namespace App\Observers;

use App\Models\Organization;
use Illuminate\Support\Facades\Schema;

class OrganizationObserver
{
    public function creating(Organization $organization): void
    {
        if (empty($organization->slug)) {
            $organization->slug = Organization::generateUniqueOrganizationSlug($organization->name);
        }
    }

    public function updating(Organization $organization): void
    {
        if ($organization->isDirty('name')) {
            $organization->slug = Organization::generateUniqueOrganizationSlug($organization->name, $organization->id);
        }
    }

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
