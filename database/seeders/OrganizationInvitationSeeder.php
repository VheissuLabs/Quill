<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Invitations in every state for each organization: pending for a stranger, pending for an
 * existing user who is not yet a member (they see the banner on their dashboard), expired and
 * accepted.
 */
class OrganizationInvitationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::with('members')->get()->each(function (Organization $organization) {
            $inviter = $organization->owners()->first();
            $outsider = User::whereNotIn('id', $organization->members->pluck('id'))->inRandomOrder()->first();

            $invitation = OrganizationInvitation::factory()->for($organization)->expiresIn(7);

            $invitation->create(['invited_by' => $inviter->id]);

            if ($outsider) {
                $invitation->create(['invited_by' => $inviter->id, 'email' => $outsider->email]);
            }

            OrganizationInvitation::factory()->for($organization)->expired()->create(['invited_by' => $inviter->id, 'created_at' => now()->subWeeks(3)]);

            OrganizationInvitation::factory()->for($organization)->accepted()->create([
                'invited_by' => $inviter->id,
                'email' => $organization->members->random()->email,
                'created_at' => now()->subDays(10),
            ]);
        });
    }
}
