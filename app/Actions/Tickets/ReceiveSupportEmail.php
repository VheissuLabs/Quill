<?php

namespace App\Actions\Tickets;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailDirection;
use App\Enums\TicketStatus;
use App\Models\ClientContact;
use App\Models\ClientDomain;
use App\Models\Comment;
use App\Models\EmailAddress;
use App\Models\EmailMessage;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReceived;
use App\Support\InboundEmail;
use App\Support\ResendInbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiveSupportEmail
{
    public function __construct(private ResendInbox $inbox) {}

    public function handle(string $resendId): ?Comment
    {
        $organization = Organization::supportInbox();

        if (! $organization || EmailMessage::query()->where('resend_id', $resendId)->exists()) {
            return null;
        }

        $email = $this->inbox->email($resendId);

        if ($email->isAutomated() || $email->fromEmail() === strtolower((string) config('services.resend.support_address'))) {
            return null;
        }

        $attachments = $this->storeAttachments($email);
        $requester = $this->userFor($email->fromEmail());
        $existing = $this->existingTicket($organization, $email);

        $comment = DB::transaction(function () use ($organization, $email, $attachments, $requester, $existing) {
            $contact = $existing ? null : $this->contactFor($organization, $email);

            $ticket = $existing ?? $organization->tickets()->create([
                'client_id' => $contact?->client_id,
                'client_contact_id' => $contact?->getKey(),
                'subject' => $email->subject(),
                'status' => TicketStatus::Open,
                'requester_name' => $email->fromName(),
                'requester_email' => $email->fromEmail(),
                'requester_user_id' => $requester?->getKey(),
            ]);

            $comment = $ticket->comments()->create([
                'body' => $email->visibleHtml(),
                'is_internal' => false,
                'user_id' => $requester?->getKey(),
            ]);

            $comment->emailMessage()->create([
                'direction' => EmailDirection::Inbound,
                'delivery_status' => EmailDeliveryStatus::Received,
                'from_email' => $email->fromEmail(),
                'from_name' => $email->fromName(),
                'to' => $email->to(),
                'cc' => $email->cc(),
                'message_id' => $email->messageId(),
                'in_reply_to' => $email->inReplyTo(),
                'references' => $email->references(),
                'resend_id' => $email->resendId(),
                'body_html' => $email->html(),
            ])->attachments()->createMany($attachments);

            $ticket->recordCustomerMessage();

            return $comment;
        });

        $ticket = $comment->commentable;

        if ($ticket instanceof Ticket) {
            Notification::send($ticket->watchers(), new TicketReceived($comment, isNewTicket: $existing === null));
        }

        return $comment;
    }

    private function existingTicket(Organization $organization, InboundEmail $email): ?Ticket
    {
        $addressedId = collect($email->recipients())
            ->map(fn (string $recipient) => Ticket::addressedBy($recipient))
            ->filter()
            ->first();

        $addressed = $addressedId
            ? $organization->tickets()->whereKey($addressedId)->where('requester_email', $email->fromEmail())->first()
            : null;

        $referenced = $addressed ? null : EmailMessage::ticketReferencedBy($email->referencedMessageIds());

        return $addressed ?? ($referenced?->organization_id === $organization->getKey() ? $referenced : null);
    }

    private function contactFor(Organization $organization, InboundEmail $email): ?ClientContact
    {
        return $organization->clientContacts()->firstWhere('email', $email->fromEmail())
            ?? ClientDomain::clientFor($organization, $email->fromEmail())?->contacts()->create([
                'name' => $email->fromName(),
                'email' => $email->fromEmail(),
            ]);
    }

    private function userFor(string $email): ?User
    {
        return User::query()->where('email', $email)->first()
            ?? EmailAddress::query()->verified()->where('email', $email)->first()?->user;
    }

    /** @return array<int, array{filename: string, content_type: string, size: int, path: string}> */
    private function storeAttachments(InboundEmail $email): array
    {
        return collect($email->attachments())
            ->map(function (array $attachment) use ($email) {
                $path = 'ticket-attachments/'.$email->resendId().'/'.$attachment['id'].'-'.Str::slug(pathinfo($attachment['filename'], PATHINFO_FILENAME)).'.'.Str::lower(pathinfo($attachment['filename'], PATHINFO_EXTENSION) ?: 'bin');
                $contents = $this->inbox->attachmentContents($email->resendId(), $attachment['id']);

                Storage::put($path, $contents);

                return [
                    'filename' => $attachment['filename'],
                    'content_type' => $attachment['content_type'],
                    'size' => $attachment['size'] ?: strlen($contents),
                    'path' => $path,
                ];
            })
            ->all();
    }
}
