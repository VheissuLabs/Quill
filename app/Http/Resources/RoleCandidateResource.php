<?php

namespace App\Http\Resources;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin User */
class RoleCandidateResource extends JsonResource
{
    public function __construct(User $resource, private Organization $organization)
    {
        parent::__construct($resource);
    }

    /** @return array<int, array<string, mixed>> */
    public static function forRole(Organization $organization, Role $role, User $viewer): array
    {
        return $organization->memberships()
            ->with(['user.organizationRoles' => fn ($query) => $query->wherePivot('organization_id', $organization->id)])
            ->get()
            ->each(fn (OrganizationMembership $membership) => $membership->setRelation('organization', $organization))
            ->filter(fn (OrganizationMembership $membership) => $membership->user->organizationRole($organization)?->id !== $role->id)
            ->filter(fn (OrganizationMembership $membership) => $viewer->can('update', [$membership, $role]))
            ->sortBy(fn (OrganizationMembership $membership) => $membership->user->name)
            ->map(fn (OrganizationMembership $membership) => (new self($membership->user, $organization))->resolve())
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->organizationRole($this->organization)?->name,
        ];
    }
}
