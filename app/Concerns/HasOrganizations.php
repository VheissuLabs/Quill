<?php

namespace App\Concerns;

use App\Data\OrganizationPermissions;
use App\Data\UserOrganization;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function personalOrganization(): ?Organization
    {
        return $this->organizations()
            ->where('is_personal', true)
            ->first();
    }

    public function switchOrganization(Organization $organization): bool
    {
        if (! $this->belongsToOrganization($organization)) {
            return false;
        }

        $this->update(['current_organization_id' => $organization->id]);
        $this->setRelation('currentOrganization', $organization);

        return true;
    }

    public function isCurrentOrganization(Organization $organization): bool
    {
        return $this->current_organization_id === $organization->id;
    }

    public function fallbackOrganization(?Organization $excluding = null): ?Organization
    {
        return $this->organizations()
            ->when($excluding, fn ($query) => $query->where('organizations.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(organizations.name)')
            ->first();
    }

    /** @return Collection<int, UserOrganization> */
    public function toUserOrganizations(bool $includeCurrent = false): Collection
    {
        return $this->organizations()
            ->get()
            ->map(fn (Organization $organization) => ! $includeCurrent && $this->isCurrentOrganization($organization)
                ? null
                : $this->toUserOrganization($organization))
            ->filter()
            ->values();
    }

    public function toUserOrganization(Organization $organization): UserOrganization
    {
        $role = $this->organizationRole($organization);

        return new UserOrganization(
            id: $organization->id,
            name: $organization->name,
            slug: $organization->slug,
            isPersonal: $organization->is_personal,
            role: $role?->value,
            roleLabel: $role?->label(),
            isCurrent: $this->isCurrentOrganization($organization),
        );
    }

    public function toOrganizationPermissions(Organization $organization): OrganizationPermissions
    {
        $role = $this->organizationRole($organization);

        return new OrganizationPermissions(
            canUpdateOrganization: $role?->hasPermission(OrganizationPermission::UpdateOrganization) ?? false,
            canDeleteOrganization: $role?->hasPermission(OrganizationPermission::DeleteOrganization) ?? false,
            canAddMember: $role?->hasPermission(OrganizationPermission::AddMember) ?? false,
            canUpdateMember: $role?->hasPermission(OrganizationPermission::UpdateMember) ?? false,
            canRemoveMember: $role?->hasPermission(OrganizationPermission::RemoveMember) ?? false,
            canCreateInvitation: $role?->hasPermission(OrganizationPermission::CreateInvitation) ?? false,
            canCancelInvitation: $role?->hasPermission(OrganizationPermission::CancelInvitation) ?? false,
            canCreateClient: $role?->hasPermission(OrganizationPermission::CreateClient) ?? false,
            canUpdateClient: $role?->hasPermission(OrganizationPermission::UpdateClient) ?? false,
            canDeleteClient: $role?->hasPermission(OrganizationPermission::DeleteClient) ?? false,
        );
    }
}
