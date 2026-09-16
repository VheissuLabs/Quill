<?php

namespace App\Http\Resources;

use App\Models\OrganizationInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationInvitation */
class OrganizationInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'email' => $this->email,
            'role' => $this->role->name,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
