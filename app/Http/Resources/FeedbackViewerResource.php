<?php

namespace App\Http\Resources;

use App\Enums\OrganizationPermission;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Feedback */
class FeedbackViewerResource extends JsonResource
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
        $organization = $this->project->organization;

        return [
            'isMember' => $this->viewer?->belongsToOrganization($organization) ?? false,
            'canUpdate' => $this->viewer?->can('update', $this->resource) ?? false,
            'canUpdateStatus' => $this->viewer?->can('updateStatus', $this->resource) ?? false,
            'canDelete' => $this->viewer?->can('delete', $this->resource) ?? false,
            'canVote' => $this->viewer?->can('vote', $this->resource) ?? false,
            'canComment' => $this->viewer?->can('comment', $this->resource) ?? false,
            'canCommentInternally' => $this->viewer?->can('commentInternally', $this->resource) ?? false,
            'canConvert' => $this->viewer?->can('convert', $this->resource) ?? false,
            'canViewActivity' => $this->viewer?->hasOrganizationPermission($organization, OrganizationPermission::ViewActivity) ?? false,
        ];
    }
}
