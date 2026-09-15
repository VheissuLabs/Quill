<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function view(User $user, Client $client): bool
    {
        return $user->belongsToOrganization($client->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ManageClients);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasOrganizationPermission($client->organization, OrganizationPermission::ManageClients);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasOrganizationPermission($client->organization, OrganizationPermission::ManageClients);
    }
}
