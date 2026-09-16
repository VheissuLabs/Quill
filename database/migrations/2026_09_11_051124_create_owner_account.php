<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: makes sure the built-in roles exist and that Karl's account and the
 * VheissuLabs organization are present in every environment. Activity logging is paused because
 * the activity log table is created by a later migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        activity()->disableLogging();

        (new PermissionsSeeder)->run();

        $karl = User::firstOrCreate(['email' => 'karl@vheissulabs.com'], [
            'name' => 'Karl Murray',
            'password' => 'password',
        ]);

        $organization = Organization::firstOrCreate(['slug' => 'vheissulabs'], ['name' => 'VheissuLabs']);

        $organization->addMember($karl, $organization->ownerRole());

        if (! $karl->current_organization_id) {
            $karl->switchOrganization($organization);
        }

        activity()->enableLogging();
    }

    public function down(): void
    {
        //
    }
};
