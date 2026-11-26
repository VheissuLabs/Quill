<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contentType' => $this->content_type,
            'size' => $this->size,
            'isImage' => $this->isImage(),
            'uploadedBy' => $this->uploader?->name,
            'uploadedAt' => $this->created_at?->toIso8601String(),
            'url' => route('documents.show', ['current_organization' => $this->organization, 'document' => $this]),
        ];
    }
}
