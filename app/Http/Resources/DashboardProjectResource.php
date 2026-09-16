<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;

/** @mixin Project */
class DashboardProjectResource extends ProjectListResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'openFeaturesCount' => $this->whenCounted('open_features'),
        ];
    }
}
