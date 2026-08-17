<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * The starting catalogue and the starting bundles. Both are rows from here on:
     * an organization edits its own roles, and new permissions are inserted, not
     * declared in code.
     *
     * @var array<string, array<int, string>>
     */
    protected array $bundles = [
        'owner' => ['*'],
        'admin' => [
            'organization:update',
            'member:add',
            'member:update',
            'member:remove',
            'invitation:create',
            'invitation:cancel',
            'activity:view',
            'team:create',
            'team:update',
            'project:create',
            'project:update',
            'client:create',
            'client:update',
        ],
        'member' => [],
        'client' => [],
    ];

    /** @var array<int, string> */
    protected array $catalogue = [
        'organization:update',
        'organization:delete',
        'member:add',
        'member:update',
        'member:remove',
        'invitation:create',
        'invitation:cancel',
        'activity:view',
        'team:create',
        'team:update',
        'team:delete',
        'project:create',
        'project:update',
        'project:delete',
        'client:create',
        'client:update',
        'client:delete',
    ];

    public function run(): void
    {
        foreach ($this->catalogue as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $previous = getPermissionsTeamId();

        setPermissionsTeamId(null);

        try {
            foreach ($this->bundles as $name => $permissions) {
                Role::findOrCreate($name, 'web')->syncPermissions(
                    $permissions === ['*'] ? $this->catalogue : $permissions
                );
            }
        } finally {
            setPermissionsTeamId($previous);
        }
    }
}
