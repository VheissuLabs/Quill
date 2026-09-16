<?php

namespace App\Concerns;

use App\Data\OrganizationPermissions;
use App\Data\UserOrganization;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

trait HasOrganizations
{
    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members', 'user_id', 'organization_id')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Organization, $this> */
    public function ownedOrganizations(): BelongsToMany
    {
        return $this->organizations()
            ->whereIn('organizations.id', $this->organizationRoles()
                ->where('is_owner', true)
                ->pluck('model_has_roles.organization_id'));
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'user_id');
    }

    /** @return MorphToMany<Role, $this> */
    public function organizationRoles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles', 'model_id', 'role_id')
            ->withPivot('organization_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function switchOrganization(?Organization $organization): bool
    {
        if ($organization && ! $this->belongsToOrganization($organization)) {
            return false;
        }

        $this->update(['current_organization_id' => $organization?->getKey()]);
        $this->setRelation('currentOrganization', $organization);

        URL::defaults(['current_organization' => $organization?->slug]);

        return true;
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function isCurrentOrganization(Organization $organization): bool
    {
        return $this->current_organization_id === $organization->id;
    }

    public function ownsOrganization(Organization $organization): bool
    {
        return $organization->isOwner($this);
    }

    public function organizationRole(Organization $organization): ?Role
    {
        return $this->relationLoaded('organizationRoles')
            ? $this->organizationRoles->firstWhere('pivot.organization_id', $organization->id)
            : $this->organizationRoles()->wherePivot('organization_id', $organization->id)->first();
    }

    public function hasOrganizationPermission(Organization $organization, OrganizationPermission $permission): bool
    {
        return $this->organizationRoles()
            ->wherePivot('organization_id', $organization->id)
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission->value))
            ->exists();
    }

    /** @return Collection<int, int> */
    public function organizationIdsWithPermission(OrganizationPermission $permission): Collection
    {
        return $this->organizationRoles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission->value))
            ->pluck('model_has_roles.organization_id');
    }

    /** @return Collection<int, UserOrganization> */
    public function toUserOrganizations(bool $includeCurrent = false): Collection
    {
        $this->loadMissing('organizationRoles');

        return $this->organizations()
            ->get()
            ->map(fn (Organization $organization) => ! $includeCurrent && $this->isCurrentOrganization($organization) ? null : $this->toUserOrganization($organization))
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
            role: $role?->name,
            isOwner: (bool) $role?->is_owner,
            canUpdate: $this->hasOrganizationPermission($organization, OrganizationPermission::UpdateOrganization),
            canManageRoles: $this->hasOrganizationPermission($organization, OrganizationPermission::ManageRoles),
            modules: $organization->moduleSettings(),
            isCurrent: $this->isCurrentOrganization($organization),
        );
    }

    public function toOrganizationPermissions(Organization $organization): OrganizationPermissions
    {
        $granted = $this->organizationRoles()
            ->wherePivot('organization_id', $organization->id)
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->map(fn (string $name) => OrganizationPermission::from($name));

        return new OrganizationPermissions(
            canUpdateOrganization: $granted->contains(OrganizationPermission::UpdateOrganization),
            canDeleteOrganization: $granted->contains(OrganizationPermission::DeleteOrganization),
            canAddMember: $granted->contains(OrganizationPermission::AddMember),
            canUpdateMember: $granted->contains(OrganizationPermission::UpdateMember),
            canRemoveMember: $granted->contains(OrganizationPermission::RemoveMember),
            canCreateInvitation: $granted->contains(OrganizationPermission::CreateInvitation),
            canCancelInvitation: $granted->contains(OrganizationPermission::CancelInvitation),
            canManageTeams: $granted->contains(OrganizationPermission::ManageTeams),
            canManageClients: $granted->contains(OrganizationPermission::ManageClients),
            canManageProjects: $granted->contains(OrganizationPermission::ManageProjects),
            canManageFeatures: $granted->contains(OrganizationPermission::ManageFeatures),
            canManageIssues: $granted->contains(OrganizationPermission::ManageIssues),
            canManageSprints: $granted->contains(OrganizationPermission::ManageSprints),
            canViewActivity: $granted->contains(OrganizationPermission::ViewActivity),
            canManageRoles: $granted->contains(OrganizationPermission::ManageRoles),
        );
    }

    public function fallbackOrganization(?Organization $excluding = null): ?Organization
    {
        return $this->organizations()
            ->when($excluding, fn ($query) => $query->where('organizations.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(organizations.name)')
            ->first();
    }

    public function switchToFallbackOrganization(?Organization $excluding = null): void
    {
        $this->switchOrganization($this->fallbackOrganization($excluding));
    }
}
