<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'isOwner' => $this->name === 'Owner',
            'permissions' => $this->permissions->pluck('name')->values(),
            'membersCount' => $this->users_count,
            'members' => $this->users->map(fn (Model $member) => new UserContactResource($member)),
        ];
    }
}
