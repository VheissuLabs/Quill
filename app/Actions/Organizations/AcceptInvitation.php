<?php

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
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
