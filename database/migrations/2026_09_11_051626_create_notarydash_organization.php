<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: adds Karl's NotaryDash email address to his account and makes him
 * the owner of the NotaryDash organization in every environment.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        activity()->disableLogging();

        $karl = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

        $karl->emailAddresses()->updateOrCreate(
            ['email' => 'karl@notarydash.com'],
            ['verified_at' => now()],
        );

        $organization = Organization::firstOrCreate(['slug' => 'notarydash'], ['name' => 'NotaryDash']);

        $organization->addMember($karl, OrganizationRole::Owner);

        activity()->enableLogging();
    }

    /**
     * Reverse the migrations. The organization and address are kept on purpose.
     */
    public function down(): void
    {
        //
    }
};
