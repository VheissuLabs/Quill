<?php

namespace App\Http\Resources;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationMembership */
class OrganizationMemberResource extends JsonResource
{
    public function __construct(OrganizationMembership $resource, private User $viewer)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $member = $this->user;
        $organization = $this->organization;
        $role = $member->organizationRole($organization);

        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->avatar ?? null,
            'role' => $role?->name,
            'isOwner' => $role !== null && $organization->isOwnerRole($role),
            'canUpdate' => $this->viewer->can('update', $this->resource),
            'canRemove' => $this->viewer->can('delete', $this->resource),
        ];
    }
}
