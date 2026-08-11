<?php

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    /**
     * Turn an accepted invitation into a membership.
     *
     * `client_id` and the role are written together: a Client-role membership
     * without a client represents nobody, and the read tools would report it as a
     * contact for an unknown client.
     */
    public function handle(User $user, OrganizationInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation) {
            $organization = $invitation->organization;

            $organization->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['client_id' => $invitation->client_id],
            );

            $user->assignOrganizationRole($organization, $invitation->role);

            $invitation->update(['accepted_at' => now()]);

            $user->switchOrganization($organization);
        });
    }
}
