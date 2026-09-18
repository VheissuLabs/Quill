<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;

/** @mixin Project */
class ProjectListResource extends ProjectResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'visibilityLabel' => $this->visibility->label(),
            'clientName' => $this->client?->name,
            'feedbackCount' => $this->whenCounted('feedback'),
        ];
    }
}
