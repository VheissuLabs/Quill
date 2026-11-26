<?php

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimeEntry */
class TimeEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->client_id,
            'clientName' => $this->whenLoaded('client', fn () => $this->client->name),
            'completedAt' => $this->completed_at->toDateString(),
            'hours' => $this->hours,
            'description' => $this->description,
            'referenceUrl' => $this->reference_url,
            'issueId' => $this->issue_id,
            'workTypeId' => $this->work_type_id,
            'userId' => $this->user_id,
            'invoiceLineId' => $this->invoice_line_id,
            'invoiceNumber' => $this->whenLoaded('invoiceLine', fn () => $this->invoiceLine?->invoice->number),
            'billed' => $this->invoice_line_id !== null,
        ];
    }
}
