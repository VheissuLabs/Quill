<?php

namespace App\Http\Resources;

use App\Models\Address;
use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDomain;
use App\Models\Comment;
use App\Models\EmailAttachment;
use App\Models\Feedback;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\WorkType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class PaletteResultResource extends JsonResource
{
    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    public function toArray(Request $request): array
    {
        return match (true) {
            $this->resource instanceof Issue => $this->issue($this->resource),
            $this->resource instanceof Feedback => $this->feedback($this->resource),
            $this->resource instanceof Project => $this->project($this->resource),
            $this->resource instanceof Sprint => $this->sprint($this->resource),
            $this->resource instanceof Team => $this->team($this->resource),
            $this->resource instanceof Client => $this->client($this->resource),
            $this->resource instanceof ClientContact => $this->contact($this->resource),
            $this->resource instanceof ClientDomain => $this->clientDomain($this->resource),
            $this->resource instanceof Address => $this->address($this->resource),
            $this->resource instanceof BoardColumn => $this->boardColumn($this->resource),
            $this->resource instanceof Ticket => $this->ticket($this->resource),
            $this->resource instanceof EmailAttachment => $this->attachment($this->resource),
            $this->resource instanceof Comment => $this->comment($this->resource),
            $this->resource instanceof Invoice => $this->invoice($this->resource),
            $this->resource instanceof InvoiceLine => $this->invoiceLine($this->resource),
            $this->resource instanceof InvoicePayment => $this->payment($this->resource),
            $this->resource instanceof TimeEntry => $this->timeEntry($this->resource),
            $this->resource instanceof WorkType => $this->workType($this->resource),
            $this->resource instanceof OrganizationMembership => $this->member($this->resource),
            $this->resource instanceof Role => $this->role($this->resource),
            $this->resource instanceof Organization => $this->organization($this->resource),
            default => throw new RuntimeException('Unsearchable record in palette results.'),
        };
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function issue(Issue $issue): array
    {
        return [
            'type' => 'issue',
            'title' => $issue->title,
            'subtitle' => "{$issue->project->name} #{$issue->number}",
            'url' => route('issues.show', [
                'current_organization' => $issue->project->organization,
                'issue' => $issue,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function feedback(Feedback $feedback): array
    {
        return [
            'type' => 'feedback',
            'title' => $feedback->title,
            'subtitle' => $feedback->project->name,
            'url' => route('portal.feedback.show', [
                'organization' => $feedback->project->organization,
                'project' => $feedback->project,
                'feedback' => $feedback,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function project(Project $project): array
    {
        return [
            'type' => 'project',
            'title' => $project->name,
            'subtitle' => $project->client?->name,
            'url' => route('portal.projects.show', [
                'organization' => $project->organization,
                'project' => $project,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function sprint(Sprint $sprint): array
    {
        return [
            'type' => 'sprint',
            'title' => $sprint->name,
            'subtitle' => $sprint->owner->name,
            'url' => route('sprints.show', [
                'current_organization' => $sprint->organization(),
                'sprint' => $sprint,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function team(Team $team): array
    {
        return [
            'type' => 'team',
            'title' => $team->name,
            'subtitle' => null,
            'url' => route('teams.edit', [
                'current_organization' => $team->organization,
                'team' => $team,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function client(Client $client): array
    {
        return [
            'type' => 'client',
            'title' => $client->name,
            'subtitle' => null,
            'url' => route('clients.show', [
                'current_organization' => $client->organization,
                'client' => $client,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function contact(ClientContact $contact): array
    {
        return [
            'type' => 'contact',
            'title' => $contact->displayName(),
            'subtitle' => $contact->client->name,
            'url' => route('clients.contacts.show', [
                'current_organization' => $contact->client->organization,
                'client' => $contact->client,
                'contact' => $contact,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function clientDomain(ClientDomain $domain): array
    {
        return [
            'type' => 'domain',
            'title' => $domain->domain,
            'subtitle' => $domain->client->name,
            'url' => route('clients.edit', ['current_organization' => $domain->client->organization, 'client' => $domain->client]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function address(Address $address): array
    {
        $owner = $address->addressable;

        return [
            'type' => 'address',
            'title' => implode(', ', array_filter([$address->line1, $address->city])),
            'subtitle' => match (true) {
                $owner instanceof ClientContact => $owner->displayName(),
                $owner instanceof Client, $owner instanceof Organization => $owner->name,
                default => null,
            },
            'url' => match (true) {
                $owner instanceof ClientContact => route('clients.contacts.show', ['current_organization' => $owner->client->organization, 'client' => $owner->client, 'contact' => $owner]),
                $owner instanceof Client => route('clients.show', ['current_organization' => $owner->organization, 'client' => $owner]),
                $owner instanceof Organization => route('organizations.billing.edit', ['current_organization' => $owner]),
                default => throw new RuntimeException('Address without a searchable owner.'),
            },
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function boardColumn(BoardColumn $column): array
    {
        $team = $column->owner;

        if (! $team instanceof Team) {
            throw new RuntimeException('Board column without a team.');
        }

        return [
            'type' => 'column',
            'title' => $column->name,
            'subtitle' => $team->name,
            'url' => route('teams.edit', ['current_organization' => $team->organization, 'team' => $team]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function ticket(Ticket $ticket): array
    {
        return [
            'type' => 'ticket',
            'title' => $ticket->subject,
            'subtitle' => "{$ticket->reference()} · ".($ticket->requester_name ?? $ticket->requester_email),
            'url' => route('tickets.show', ['current_organization' => $ticket->organization, 'ticket' => $ticket]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function attachment(EmailAttachment $attachment): array
    {
        $ticket = $attachment->emailMessage->comment->commentable;

        if (! $ticket instanceof Ticket) {
            throw new RuntimeException('Attachment outside a ticket.');
        }

        return [
            'type' => 'attachment',
            'title' => $attachment->filename,
            'subtitle' => "{$ticket->reference()} {$ticket->subject}",
            'url' => route('tickets.show', ['current_organization' => $ticket->organization, 'ticket' => $ticket]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function comment(Comment $comment): array
    {
        $parent = $comment->commentable;

        [$where, $url] = match (true) {
            $parent instanceof Issue => ["{$parent->project->name} #{$parent->number}", route('issues.show', ['current_organization' => $parent->project->organization, 'issue' => $parent])],
            $parent instanceof Feedback => [$parent->title, route('portal.feedback.show', ['organization' => $parent->project->organization, 'project' => $parent->project, 'feedback' => $parent])],
            $parent instanceof Ticket => ["{$parent->reference()} {$parent->subject}", route('tickets.show', ['current_organization' => $parent->organization, 'ticket' => $parent])],
            default => throw new RuntimeException('Comment on an unsearchable record.'),
        };

        return [
            'type' => 'comment',
            'title' => Str::limit((string) $comment->body_text, 80),
            'subtitle' => trim(($comment->author->name ?? '').' on '.$where),
            'url' => $url,
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function invoice(Invoice $invoice): array
    {
        return [
            'type' => 'invoice',
            'title' => $invoice->number,
            'subtitle' => $invoice->client->name,
            'url' => route('invoices.show', ['current_organization' => $invoice->organization, 'invoice' => $invoice]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function invoiceLine(InvoiceLine $line): array
    {
        return [
            'type' => 'invoice line',
            'title' => $line->description,
            'subtitle' => $line->invoice->number,
            'url' => route('invoices.show', ['current_organization' => $line->invoice->organization, 'invoice' => $line->invoice]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function payment(InvoicePayment $payment): array
    {
        return [
            'type' => 'payment',
            'title' => $payment->reference ?? Str::limit((string) $payment->notes, 80),
            'subtitle' => $payment->invoice->number,
            'url' => route('invoices.show', ['current_organization' => $payment->invoice->organization, 'invoice' => $payment->invoice]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function timeEntry(TimeEntry $entry): array
    {
        return [
            'type' => 'time',
            'title' => (string) $entry->description,
            'subtitle' => "{$entry->client->name} · {$entry->completed_at->format('M j')} · {$entry->hours}h unbilled",
            'url' => route('clients.show', ['current_organization' => $entry->client->organization, 'client' => $entry->client]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function workType(WorkType $workType): array
    {
        return [
            'type' => 'work type',
            'title' => $workType->name,
            'subtitle' => null,
            'url' => route('organizations.billing.edit', ['current_organization' => $workType->organization]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function member(OrganizationMembership $membership): array
    {
        $role = $membership->user->organizationRoles->first();

        return [
            'type' => 'member',
            'title' => $membership->user->name,
            'subtitle' => trim($membership->user->email.($role ? " · {$role->name}" : '')),
            'url' => $role
                ? route('roles.show', ['current_organization' => $membership->organization, 'role' => $role])
                : route('roles.index', ['current_organization' => $membership->organization]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function role(Role $role): array
    {
        $organization = $role->getRelation('organization');

        if (! $organization instanceof Organization) {
            throw new RuntimeException('Role without its organization.');
        }

        return [
            'type' => 'role',
            'title' => $role->name,
            'subtitle' => null,
            'url' => route('roles.show', ['current_organization' => $organization, 'role' => $role]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function organization(Organization $organization): array
    {
        return [
            'type' => 'organization',
            'title' => $organization->name,
            'subtitle' => null,
            'url' => route('dashboard', ['current_organization' => $organization]),
        ];
    }
}
