<?php

namespace App\Actions\Organizations;

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
            foreach ($this->defaults() as $name => $permissions) {
                Role::findOrCreate($name, 'web')->syncPermissions($permissions);
            }
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }

    /** @return array<string, array<int, string>> */
    protected function defaults(): array
    {
        $catalog = $this->catalog();

        return collect((array) config('roles.defaults'))
            ->map(fn (array|string $permissions) => $permissions === '*' ? $catalog : (array) $permissions)
            ->all();
    }

    protected function ensurePermissionsExist(): void
    {
        $existing = Permission::pluck('name');

        foreach ($this->catalog() as $permission) {
            if (! $existing->contains($permission)) {
                Permission::create(['name' => $permission, 'guard_name' => 'web']);
            }
        }
    }

    /** @return array<int, string> */
    protected function catalog(): array
    {
        return (array) config('roles.permissions');
    }
}
