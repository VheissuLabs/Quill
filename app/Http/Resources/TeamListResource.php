<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;

/** @mixin Team */
class TeamListResource extends TeamResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'membersCount' => $this->whenCounted('members'),
        ];
    }
}
