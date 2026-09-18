<?php

namespace App\Http\Resources;

use App\Enums\OrganizationPermission;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectViewerResource extends JsonResource
{
    private ?User $viewer = null;

    public function for(?User $viewer): static
    {
        $this->viewer = $viewer;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'isMember' => $this->viewer?->belongsToOrganization($this->organization) ?? false,
            'canCreateFeedback' => $this->viewer?->can('create', [Feedback::class, $this->resource]) ?? false,
            'canManageFeedback' => $this->viewer?->hasOrganizationPermission($this->organization, OrganizationPermission::ManageFeedback) ?? false,
        ];
    }
}
