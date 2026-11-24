<?php

namespace App\Http\Resources;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationSettingsResource extends JsonResource
{
    public function __construct(Organization $resource, private User $viewer)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logoUrl' => $this->logoUrl(),
            'isOwner' => $this->isOwner($this->viewer),
            'modules' => $this->moduleSettings(),
            'moduleOrder' => $this->resource->moduleOrder(),
        ];
    }
}
