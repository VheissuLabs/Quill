<?php

namespace App\Concerns;

use App\Data\OrganizationPermissions;
use App\Data\UserOrganization;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
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
            ->withPivot(['client_id'])
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

    public function assignOrganizationRole(Organization $organization, OrganizationRole|Role|string $role): void
    {
        $this->withinOrganization($organization, function () use ($role) {
            $this->unsetRelation('roles');
            $this->syncRoles([$role instanceof OrganizationRole ? $role->value : $role]);
        });
    }

    public function organizationRole(Organization $organization): ?Role
    {
        return Role::query()
            ->where('organization_id', $organization->id)
            ->whereHas('users', fn ($query) => $query->whereKey($this->getKey()))
            ->first();
    }

    public function organizationRoleName(Organization $organization): ?string
    {
        return $this->organizationRole($organization)?->name;
    }

    public function ownsOrganization(Organization $organization): bool
    {
        return $this->organizationRoleName($organization) === OrganizationRole::Owner->value;
    }

    public function isClientContact(Organization $organization): bool
    {
        return $this->organizationRoleName($organization) === OrganizationRole::Client->value;
    }

    public function hasOrganizationPermission(Organization $organization, OrganizationPermission $permission): bool
    {
        if (! $this->belongsToOrganization($organization)) {
            return false;
        }

        return $this->withinOrganization($organization, function () use ($permission) {
            $this->unsetRelation('roles');
            $this->forgetCachedPermissions();

            return $this->hasPermissionTo($permission->value);
        });
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
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
            role: $role?->name,
            roleLabel: $role?->label(),
            isCurrent: $this->isCurrentOrganization($organization),
        );
    }

    public function toOrganizationPermissions(Organization $organization): OrganizationPermissions
    {
        $granted = $this->organizationPermissionNames($organization);

        return new OrganizationPermissions(
            canUpdateOrganization: $granted->contains(OrganizationPermission::UpdateOrganization->value),
            canDeleteOrganization: $granted->contains(OrganizationPermission::DeleteOrganization->value),
            canAddMember: $granted->contains(OrganizationPermission::AddMember->value),
            canUpdateMember: $granted->contains(OrganizationPermission::UpdateMember->value),
            canRemoveMember: $granted->contains(OrganizationPermission::RemoveMember->value),
            canCreateInvitation: $granted->contains(OrganizationPermission::CreateInvitation->value),
            canCancelInvitation: $granted->contains(OrganizationPermission::CancelInvitation->value),
            canCreateClient: $granted->contains(OrganizationPermission::CreateClient->value),
            canUpdateClient: $granted->contains(OrganizationPermission::UpdateClient->value),
            canDeleteClient: $granted->contains(OrganizationPermission::DeleteClient->value),
        );
    }

    /** @return Collection<int, string> */
    public function organizationPermissionNames(Organization $organization): Collection
    {
        if (! $this->belongsToOrganization($organization)) {
            return collect();
        }

        return Role::query()
            ->where('organization_id', $organization->id)
            ->whereHas('users', fn ($query) => $query->whereKey($this->getKey()))
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    /**
     * @template TReturn
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    protected function withinOrganization(Organization $organization, callable $callback): mixed
    {
        $previous = getPermissionsTeamId();

        setPermissionsTeamId($organization->id);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previous);
        }
    }
}
