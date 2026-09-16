<?php

namespace Database\Seeders;

use App\Enums\OrganizationPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every permission the application checks. Roles are not seeded here: each organization
 * owns its roles and creates its defaults when it is created.
 */
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        setPermissionsTeamId(null);

        foreach (OrganizationPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
