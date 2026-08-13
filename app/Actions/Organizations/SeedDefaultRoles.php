<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;

class SeedDefaultRoles
{
    public function handle(Organization $organization): void
    {
        $this->ensurePermissionsExist();

        $previousTeam = getPermissionsTeamId();

        setPermissionsTeamId($organization->id);

        try {
            foreach (OrganizationRole::cases() as $default) {
                $role = Role::findOrCreate($default->value, 'web');

                $role->syncPermissions(
                    collect($default->permissions())->map(fn (OrganizationPermission $permission) => $permission->value)->all()
                );
            }
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }

    protected function ensurePermissionsExist(): void
    {
        $existing = Permission::pluck('name');

        foreach (OrganizationPermission::cases() as $permission) {
            if (! $existing->contains($permission->value)) {
                Permission::create(['name' => $permission->value, 'guard_name' => 'web']);
            }
        }
    }
}
