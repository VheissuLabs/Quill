<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;

class ClientPolicy
{
    /**
     * Determine whether the user can view the organization's clients.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user can view the client.
     */
    public function view(User $user, Client $client): bool
    {
        return $user->belongsToOrganization($client->organization);
    }

    /**
     * Determine whether the user can create clients in the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ManageClients);
    }

    /**
     * Determine whether the user can update the client.
     */
    public function update(User $user, Client $client): bool
    {
        return $user->hasOrganizationPermission($client->organization, OrganizationPermission::ManageClients);
    }

    /**
     * Determine whether the user can delete the client.
     */
    public function delete(User $user, Client $client): bool
    {
        return $user->hasOrganizationPermission($client->organization, OrganizationPermission::ManageClients);
    }
}
