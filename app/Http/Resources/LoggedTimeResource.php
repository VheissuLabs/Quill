<?php

namespace App\Http\Resources;

use App\Models\Client;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimeEntry */
class LoggedTimeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['client.billedThrough', 'invoiceLine.invoice.client']);

        $invoice = $this->invoiceLine?->invoice;
        $billedClient = $this->client->billedThrough ?? $this->client;

        return [
            'logged' => __(':hours h on :date for :client: :description', [
                'hours' => (float) $this->hours,
                'date' => $this->completed_at->toDateString(),
                'client' => $this->client->name,
                'description' => $this->description,
            ]),
            'entry' => TimeEntryResource::make($this->resource)->resolve(),
            'invoice' => $invoice === null ? null : [
                ...InvoiceListResource::make($invoice)->resolve(),
                'lineId' => $this->invoice_line_id,
            ],
            'notBilled' => $invoice === null ? $this->notBilledReason($billedClient) : null,
        ];
    }

    private function notBilledReason(Client $billedClient): string
    {
        $covering = $billedClient->invoiceCovering($this->completed_at);

        return $covering === null
            ? __('Recorded, but no open invoice covers :date, so it is not billed yet.', ['date' => $this->completed_at->toDateString()])
            : __('Recorded, but :date falls in invoice :number, which has already been sent, so it is not billed.', ['date' => $this->completed_at->toDateString(), 'number' => $covering->number]);
    }
}
