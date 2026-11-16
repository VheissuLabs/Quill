<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'clientId' => $this->client_id,
            'clientName' => $this->client->name,
            'clientSlug' => $this->client->slug,
            'issueDate' => $this->issue_date->toDateString(),
            'dueDate' => $this->due_date->toDateString(),
            'total' => $this->total,
            'amountDue' => $this->amountDue(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
        ];
    }
}
