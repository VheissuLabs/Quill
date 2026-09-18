<?php

namespace Database\Seeders;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Three generated organizations. Every organization, including any a data migration created,
 * ends up with an Admin role that can run the organization and a plain Member role next to the
 * locked Owner role the observer creates.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::factory()->count(3)->create();

        Organization::all()->each(function (Organization $organization) {
            $organization->roles()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web'])->syncPermissions([
                OrganizationPermission::UpdateOrganization->value,
                OrganizationPermission::CreateInvitation->value,
                OrganizationPermission::CancelInvitation->value,
                OrganizationPermission::ManageTeams->value,
                OrganizationPermission::ManageClients->value,
                OrganizationPermission::ManageProjects->value,
                OrganizationPermission::ManageFeedback->value,
                OrganizationPermission::ManageIssues->value,
                OrganizationPermission::ManageSprints->value,
                OrganizationPermission::ViewActivity->value,
            ]);

            $organization->roles()->firstOrCreate(['name' => 'Member', 'guard_name' => 'web']);
        });
    }
}
