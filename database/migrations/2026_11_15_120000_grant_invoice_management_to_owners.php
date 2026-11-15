<?php

use App\Enums\OrganizationPermission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        setPermissionsTeamId(null);

        Permission::findOrCreate(OrganizationPermission::ManageInvoices->value);

        Role::query()
            ->with('permissions')
            ->whereIn('name', ['Owner', 'Admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo(OrganizationPermission::ManageInvoices->value));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void {}
};
