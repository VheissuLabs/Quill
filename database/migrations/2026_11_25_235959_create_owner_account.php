<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        activity()->disableLogging();
        config(['broadcasting.default' => 'null']);

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

    public function down(): void {}
};
