<?php

namespace App\Http\Resources;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Issue */
class IssueLookupResource extends JsonResource
{
    /** @return array{id: int, reference: string, title: string, statusLabel: string} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'title' => $this->title,
            'statusLabel' => $this->boardColumn->name ?? $this->status->label(),
        ];
    }
}
