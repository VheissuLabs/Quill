<?php

namespace App\Actions\Organizations;

use App\Models\Client;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\Organizations\OrganizationInvitation as InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class InviteContact
{
    public function handle(User $inviter, Client $client, string $email, ?string $name = null): OrganizationInvitation
    {
        $email = mb_strtolower(trim($email));

        $invitation = DB::transaction(function () use ($inviter, $client, $email) {
            return OrganizationInvitation::updateOrCreate(
                [
                    'organization_id' => $client->organization_id,
                    'email' => $email,
                ],
                [
                    'client_id' => $client->id,
                    'role' => 'client',
                    'invited_by' => $inviter->id,
                    'expires_at' => now()->addDays(14),
                    'accepted_at' => null,
                ],
            );
        });

        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing !== null) {
            $existing->notify(new InvitationNotification($invitation, inApp: true));
        } else {
            Notification::route('mail', $email)->notify(new InvitationNotification($invitation));
        }

        return $invitation;
    }
}
