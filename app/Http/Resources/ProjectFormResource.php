<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;

/** @mixin Project */
class ProjectFormResource extends ProjectResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'clientId' => $this->client_id,
            'teamIds' => $this->teams()->pluck('teams.id'),
        ];
    }
}
