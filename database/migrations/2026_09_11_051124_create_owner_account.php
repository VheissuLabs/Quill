<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: makes sure the built-in roles exist and that Karl's account and the
 * VheissuLabs organization are present in every environment. Activity logging is paused because
 * the activity log table is created by a later migration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        activity()->disableLogging();

        (new RolesAndPermissionsSeeder)->run();

        $karl = User::firstOrCreate(['email' => 'karl@vheissulabs.com'], [
            'name' => 'Karl Murray',
            'password' => 'password',
        ]);

        $organization = Organization::firstOrCreate(['slug' => 'vheissulabs'], ['name' => 'VheissuLabs']);

        $organization->addMember($karl, OrganizationRole::Owner);

        if (! $karl->current_organization_id) {
            $karl->switchOrganization($organization);
        }

        activity()->enableLogging();
    }

    /**
     * Reverse the migrations. The account and organization are kept on purpose.
     */
    public function down(): void
    {
        //
    }
};
