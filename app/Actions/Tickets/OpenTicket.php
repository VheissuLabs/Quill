<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\ClientContact;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OpenTicket
{
    public function __construct(private ReplyToTicket $reply) {}

    /** @param  array{client_contact_id: int|null, client_id: int|null, requester_name: string|null, requester_email: string|null, subject: string, body: string, send: bool}  $attributes */
    public function handle(Organization $organization, User $creator, array $attributes): Ticket
    {
        return DB::transaction(function () use ($organization, $creator, $attributes) {
            $contact = $this->contactFor($organization, $attributes);
            $email = strtolower($contact->email ?? (string) $attributes['requester_email']);

            $ticket = $organization->tickets()->create([
                'client_id' => $attributes['client_id'] ?? $contact?->client_id,
                'client_contact_id' => $contact?->getKey(),
                'subject' => $attributes['subject'],
                'status' => TicketStatus::Open,
                'requester_name' => $contact->name ?? $attributes['requester_name'],
                'requester_email' => $email,
                'requester_user_id' => User::query()->where('email', $email)->value('id'),
                'created_by' => $creator->getKey(),
            ]);

            $this->reply->handle($ticket, $creator, $attributes['body'], internal: ! $attributes['send']);

            return $ticket;
        });
    }

    /** @param  array{client_contact_id: int|null, client_id: int|null, requester_name: string|null, requester_email: string|null}  $attributes */
    private function contactFor(Organization $organization, array $attributes): ?ClientContact
    {
        if ($attributes['client_contact_id']) {
            return $organization->clientContacts()->findOrFail($attributes['client_contact_id']);
        }

        return $attributes['client_id']
            ? $organization->clients()->findOrFail($attributes['client_id'])->contacts()->create([
                'name' => $attributes['requester_name'],
                'email' => (string) $attributes['requester_email'],
            ])
            : null;
    }
}
