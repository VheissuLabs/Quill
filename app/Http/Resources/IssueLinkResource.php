<?php

namespace App\Http\Resources;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueLinkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Issue $issue */
        $issue = $this->resource['issue'];

        return [
            'id' => $this->resource['id'],
            'type' => $this->resource['type'],
            'label' => $this->resource['label'],
            'inverse' => $this->resource['inverse'],
            'issue' => [
                'id' => $issue->id,
                'reference' => $issue->reference(),
                'title' => $issue->title,
                'status' => $issue->status->value,
                'statusLabel' => $issue->status->label(),
            ],
        ];
    }
}
