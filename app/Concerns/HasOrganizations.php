<?php

namespace App\Concerns;

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasOrganizations
{
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members', 'user_id', 'organization_id')
            ->using(OrganizationMembership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'user_id');
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function organizationRole(Organization $organization): ?OrganizationRole
    {
        return $this->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->first()
            ?->role;
    }

    public function ownsOrganization(Organization $organization): bool
    {
        return $this->organizationRole($organization) === OrganizationRole::Owner;
    }

    public function isClientContact(Organization $organization): bool
    {
        return $this->organizationRole($organization) === OrganizationRole::Client;
    }

    public function hasOrganizationPermission(Organization $organization, OrganizationPermission $permission): bool
    {
        return $this->organizationRole($organization)?->hasPermission($permission) ?? false;
    }
}
