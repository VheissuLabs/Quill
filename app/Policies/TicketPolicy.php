<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->manages($user, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->manages($user, $organization);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->manages($user, $ticket->organization);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->manages($user, $ticket->organization);
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->manages($user, $ticket->organization);
    }

    public function convert(User $user, Ticket $ticket): bool
    {
        return $this->manages($user, $ticket->organization)
            && $ticket->organization->canUse(OrganizationCapability::Issues);
    }

    private function manages(User $user, Organization $organization): bool
    {
        return $organization->receivesSupportMail()
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ManageTickets);
    }
}
