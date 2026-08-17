<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Collection;

class SeedDefaultRoles
{
    public function handle(Organization $organization): void
    {
        $previous = getPermissionsTeamId();

        setPermissionsTeamId($organization->id);

        try {
            foreach ($this->templates() as $template) {
                /**
                 * Created straight through Eloquent: Spatie's findOrCreate would match
                 * the unscoped template and hand back the shared row instead of making
                 * this organization its own copy.
                 */
                Role::firstOrCreate([
                    'name' => $template->name,
                    'guard_name' => 'web',
                    'organization_id' => $organization->id,
                ])->syncPermissions($template->permissions->pluck('name')->all());
            }
        } finally {
            setPermissionsTeamId($previous);
        }
    }

    /**
     * The unscoped roles are the starting point every organization is given a copy
     * of, so an owner can reshape their own without touching anyone else's.
     *
     * @return Collection<int, Role>
     */
    protected function templates(): Collection
    {
        $templates = $this->unscopedRoles();

        if ($templates->isEmpty()) {
            new RoleSeeder()->run();

            $templates = $this->unscopedRoles();
        }

        return $templates;
    }

    /** @return Collection<int, Role> */
    protected function unscopedRoles(): Collection
    {
        return Role::query()
            ->whereNull('organization_id')
            ->with('permissions')
            ->get();
    }
}
