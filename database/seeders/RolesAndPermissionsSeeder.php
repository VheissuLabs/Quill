<?php

namespace Database\Seeders;

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the built-in organization roles and grants each its default permissions.
 * Safe to rerun; existing roles keep their name and are re-synced to the defaults below.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        setPermissionsTeamId(null);

        foreach (OrganizationPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->defaultPermissions() as $roleName => $permissions) {
            Role::findOrCreate($roleName)->syncPermissions(
                array_map(fn (OrganizationPermission $permission) => $permission->value, $permissions),
            );
        }
    }

    /**
     * The permissions granted to each built-in role.
     *
     * @return array<string, array<int, OrganizationPermission>>
     */
    protected function defaultPermissions(): array
    {
        return [
            OrganizationRole::Owner->value => OrganizationPermission::cases(),
            OrganizationRole::Admin->value => [
                OrganizationPermission::UpdateOrganization,
                OrganizationPermission::CreateInvitation,
                OrganizationPermission::CancelInvitation,
                OrganizationPermission::ManageTeams,
                OrganizationPermission::ManageClients,
                OrganizationPermission::ManageProjects,
                OrganizationPermission::ManageFeatures,
                OrganizationPermission::ManageIssues,
                OrganizationPermission::ManageSprints,
                OrganizationPermission::ViewActivity,
            ],
            OrganizationRole::Member->value => [],
        ];
    }
}
