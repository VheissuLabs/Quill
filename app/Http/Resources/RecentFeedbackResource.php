<?php

namespace App\Http\Resources;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Feedback */
class RecentFeedbackResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'votesCount' => $this->whenCounted('votes'),
            'createdAt' => $this->created_at->toISOString(),
            'project' => ['name' => $this->project->name, 'slug' => $this->project->slug],
        ];
    }
}
