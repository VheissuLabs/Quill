<?php

namespace App\Http\Resources;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class UserOrganizationResource extends JsonResource
{
    public function __construct(Organization $resource, private User $viewer)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = $this->viewer->organizationRole($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'role' => $role?->name,
            'isOwner' => $role !== null && $this->resource->isOwnerRole($role),
            'canUpdate' => $this->viewer->hasOrganizationPermission($this->resource, OrganizationPermission::UpdateOrganization),
            'canManageRoles' => $this->viewer->hasOrganizationPermission($this->resource, OrganizationPermission::ManageRoles),
            'receivesSupportMail' => $this->resource->receivesSupportMail(),
            'modules' => $this->moduleSettings(),
            'isCurrent' => $this->viewer->isCurrentOrganization($this->resource),
        ];
    }
}
