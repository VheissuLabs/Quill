<?php

namespace App\Http\Resources;

use App\Models\Client;
use App\Models\Issue;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class LinkedTicketResource extends JsonResource
{
    /** @return array<int, array<string, mixed>> */
    public static function forIssue(Issue $issue, User $viewer): array
    {
        if (! $viewer->can('viewAny', [Ticket::class, $issue->project->organization])) {
            return [];
        }

        return $issue->tickets()
            ->with('client')
            ->latest('last_message_at')
            ->get()
            ->map(fn (Ticket $ticket) => self::make($ticket)->resolve())
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public static function forClient(Client $client, User $viewer): array
    {
        if (! $viewer->can('viewAny', [Ticket::class, $client->organization])) {
            return [];
        }

        return $client->tickets()
            ->with('client')
            ->latest('last_message_at')
            ->limit(10)
            ->get()
            ->map(fn (Ticket $ticket) => self::make($ticket)->resolve())
            ->all();
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'subject' => $this->subject,
            'statusLabel' => $this->status->label(),
            'requester' => $this->requester_name ?? $this->requester_email,
            'clientName' => $this->client?->name,
            'readyToReply' => $this->issue_resolved_at !== null,
        ];
    }
}
