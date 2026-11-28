<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    public const WINDOW_STEP = 30;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'subject' => $this->subject,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'requesterName' => $this->requester_name,
            'requesterEmail' => $this->requester_email,
            'contactId' => $this->client_contact_id,
            'client' => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'slug' => $this->client->slug,
            ] : null,
            'issue' => $this->issue ? [
                'id' => $this->issue->id,
                'reference' => $this->issue->reference(),
                'title' => $this->issue->title,
                'status' => $this->issue->status->value,
                'statusLabel' => $this->issue->status->label(),
            ] : null,
            'reporterName' => $this->reporter?->name,
            'readyToReply' => $this->issue_resolved_at !== null,
            'lastMessageAt' => $this->last_message_at?->toISOString(),
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
