<?php

namespace App\Actions\Organizations;

use DomainException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DeleteOrganizationRole
{
    public function handle(Role $role): void
    {
        if (DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
            throw new DomainException(__('Move its members to another role before deleting this one.'));
        }

        $role->delete();
    }
}
