<?php

namespace App\Actions\Palette;

use App\Enums\OrganizationCapability;
use App\Models\Address;
use App\Models\BillingSetting;
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
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class SearchOrganization
{
    private const PER_TYPE = 5;

    private const MINIMUM_TERM = 2;

    /** @return Collection<int, Model> */
    public function handle(Organization $organization, User $user, string $term): Collection
    {
        if (mb_strlen($term) < self::MINIMUM_TERM) {
            return collect();
        }

        $projectIds = $organization->projects()->visibleTo($user)->pluck('projects.id');
        $issues = $organization->canUse(OrganizationCapability::Issues);
        $boards = $organization->canUse(OrganizationCapability::Boards);
        $tickets = $user->can('viewAny', [Ticket::class, $organization]);
        $invoices = $user->can('viewAny', [Invoice::class, $organization]);

        /** @var Collection<int, Model> $results */
        $results = collect();

        return $results
            ->concat($this->projects($organization, $user, $term))
            ->concat($issues ? $this->issues($projectIds, $term) : [])
            ->concat($boards ? $this->feedback($projectIds, $term) : [])
            ->concat($organization->canUse(OrganizationCapability::Sprints) ? $this->sprints($organization, $term) : [])
            ->concat($this->teams($organization, $term))
            ->concat($this->boardColumns($organization, $term))
            ->concat($this->clients($organization, $term))
            ->concat($this->contacts($organization, $term))
            ->concat($this->clientDomains($organization, $term))
            ->concat($this->addresses($organization, $user, $term))
            ->concat($tickets ? $this->tickets($organization, $term) : [])
            ->concat($tickets ? $this->attachments($organization, $term) : [])
            ->concat($this->comments($organization, $projectIds, $issues, $boards, $tickets, $term))
            ->concat($invoices ? $this->invoices($organization, $term) : [])
            ->concat($invoices ? $this->invoiceLines($organization, $term) : [])
            ->concat($invoices ? $this->payments($organization, $term) : [])
            ->concat($invoices ? $this->unbilledTime($organization, $term) : [])
            ->concat($user->can('viewAny', [BillingSetting::class, $organization]) ? $this->workTypes($organization, $term) : [])
            ->concat($this->members($organization, $term))
            ->concat($this->roles($organization, $term))
            ->concat($this->organizations($user, $term));
    }

    /** @return Collection<int, Project> */
    private function projects(Organization $organization, User $user, string $term): Collection
    {
        return $organization->projects()
            ->visibleTo($user)
            ->where('name', 'like', $this->wildcard($term))
            ->with(['organization', 'client'])
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return Collection<int, Issue>
     */
    private function issues(Collection $projectIds, string $term): Collection
    {
        return Issue::query()
            ->whereIn('project_id', $projectIds)
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', $this->wildcard($term))
                ->when(ctype_digit($term), fn (Builder $query) => $query->orWhere('number', (int) $term)))
            ->with('project.organization')
            ->tap(fn (Builder $query) => $this->rank($query, 'title', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return Collection<int, Feedback>
     */
    private function feedback(Collection $projectIds, string $term): Collection
    {
        return Feedback::query()
            ->whereIn('project_id', $projectIds)
            ->where('title', 'like', $this->wildcard($term))
            ->with('project.organization')
            ->tap(fn (Builder $query) => $this->rank($query, 'title', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, Sprint> */
    private function sprints(Organization $organization, string $term): Collection
    {
        return Sprint::query()
            ->onBoardFor($organization, null)
            ->where('name', 'like', $this->wildcard($term))
            ->with(['owner' => fn (MorphTo $owner) => $owner->morphWith([Team::class => ['organization']])])
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get()
            ->filter(fn (Sprint $sprint) => $sprint->runsSprints())
            ->values();
    }

    /** @return Collection<int, Team> */
    private function teams(Organization $organization, string $term): Collection
    {
        return $organization->teams()
            ->where('name', 'like', $this->wildcard($term))
            ->with('organization')
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, BoardColumn> */
    private function boardColumns(Organization $organization, string $term): Collection
    {
        return BoardColumn::query()
            ->whereHasMorph('owner', [Team::class], fn (Builder $query) => $query->where('organization_id', $organization->id))
            ->where('name', 'like', $this->wildcard($term))
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get()
            ->each(fn (BoardColumn $column) => $column->owner->setRelation('organization', $organization));
    }

    /** @return Collection<int, Client> */
    private function clients(Organization $organization, string $term): Collection
    {
        return $organization->clients()
            ->where('name', 'like', $this->wildcard($term))
            ->with('organization')
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, ClientContact> */
    private function contacts(Organization $organization, string $term): Collection
    {
        return $organization->clientContacts()
            ->whereHas('client')
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $this->wildcard($term))
                ->orWhere('email', 'like', $this->wildcard($term))
                ->orWhere('phone', 'like', $this->wildcard($term)))
            ->with('client.organization')
            ->orderByRaw('INSTR(LOWER(COALESCE(name, email)), ?)', [mb_strtolower($term)])
            ->orderByRaw('LOWER(COALESCE(name, email))')
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, ClientDomain> */
    private function clientDomains(Organization $organization, string $term): Collection
    {
        return ClientDomain::query()
            ->whereHas('client', fn (Builder $query) => $query->where('organization_id', $organization->id))
            ->where('domain', 'like', $this->wildcard($term))
            ->with('client.organization')
            ->orderBy('domain')
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, Address> */
    private function addresses(Organization $organization, User $user, string $term): Collection
    {
        $owners = [Client::class, ClientContact::class];

        if ($user->can('viewAny', [BillingSetting::class, $organization])) {
            $owners[] = Organization::class;
        }

        return Address::query()
            ->whereHasMorph('addressable', $owners, fn (Builder $query, string $type) => $type === Organization::class
                ? $query->whereKey($organization->id)
                : $query->where('organization_id', $organization->id))
            ->where(fn (Builder $query) => collect(['line1', 'line2', 'city', 'region', 'postal_code'])
                ->each(fn (string $column) => $query->orWhere($column, 'like', $this->wildcard($term))))
            ->with(['addressable' => fn (MorphTo $owner) => $owner->morphWith([Client::class => ['organization'], ClientContact::class => ['client.organization']])])
            ->limit(self::PER_TYPE)
            ->get()
            ->filter(fn (Address $address) => ! $address->addressable instanceof ClientContact || $address->addressable->client !== null)
            ->values();
    }

    /** @return Collection<int, Ticket> */
    private function tickets(Organization $organization, string $term): Collection
    {
        $number = ltrim(mb_strtoupper($term), 'T-');

        return $organization->tickets()
            ->where(fn (Builder $query) => $query
                ->where('subject', 'like', $this->wildcard($term))
                ->orWhere('requester_name', 'like', $this->wildcard($term))
                ->orWhere('requester_email', 'like', $this->wildcard($term))
                ->when(ctype_digit($number), fn (Builder $query) => $query->orWhere('number', (int) $number)))
            ->with('organization')
            ->latest('last_message_at')
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, EmailAttachment> */
    private function attachments(Organization $organization, string $term): Collection
    {
        return EmailAttachment::query()
            ->where('filename', 'like', $this->wildcard($term))
            ->whereHas('emailMessage.comment', fn (Builder $query) => $query
                ->whereHasMorph('commentable', [Ticket::class], fn (Builder $query) => $query->where('organization_id', $organization->id)))
            ->with('emailMessage.comment.commentable.organization')
            ->latest('id')
            ->limit(self::PER_TYPE)
            ->get();
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return Collection<int, Comment>
     */
    private function comments(Organization $organization, Collection $projectIds, bool $issues, bool $boards, bool $tickets, string $term): Collection
    {
        $types = array_keys(array_filter([Issue::class => $issues, Feedback::class => $boards, Ticket::class => $tickets]));

        if ($types === []) {
            return collect();
        }

        return Comment::query()
            ->where('body_text', 'like', $this->wildcard($term))
            ->whereHasMorph('commentable', $types, fn (Builder $query, string $type) => $type === Ticket::class
                ? $query->where('organization_id', $organization->id)
                : $query->whereIn('project_id', $projectIds))
            ->with(['author', 'commentable' => fn (MorphTo $commentable) => $commentable->morphWith([
                Issue::class => ['project.organization'],
                Feedback::class => ['project.organization'],
                Ticket::class => ['organization'],
            ])])
            ->latest('id')
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, Invoice> */
    private function invoices(Organization $organization, string $term): Collection
    {
        return $organization->invoices()
            ->where(fn (Builder $query) => $query
                ->where('number', 'like', $this->wildcard($term))
                ->orWhereHas('client', fn (Builder $query) => $query->withTrashed()->where('name', 'like', $this->wildcard($term))))
            ->with(['organization', 'client' => fn ($query) => $query->withTrashed()])
            ->orderByDesc('issue_date')
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, InvoiceLine> */
    private function invoiceLines(Organization $organization, string $term): Collection
    {
        return InvoiceLine::query()
            ->whereHas('invoice', fn (Builder $query) => $query->where('organization_id', $organization->id))
            ->where('description', 'like', $this->wildcard($term))
            ->with('invoice.organization')
            ->latest('id')
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, InvoicePayment> */
    private function payments(Organization $organization, string $term): Collection
    {
        return InvoicePayment::query()
            ->whereHas('invoice', fn (Builder $query) => $query->where('organization_id', $organization->id))
            ->where(fn (Builder $query) => $query
                ->where('reference', 'like', $this->wildcard($term))
                ->orWhere('notes', 'like', $this->wildcard($term)))
            ->with('invoice.organization')
            ->latest('paid_on')
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, TimeEntry> */
    private function unbilledTime(Organization $organization, string $term): Collection
    {
        return TimeEntry::query()
            ->where('organization_id', $organization->id)
            ->whereNull('invoice_line_id')
            ->whereHas('client')
            ->where('description', 'like', $this->wildcard($term))
            ->with('client.organization')
            ->latest('completed_at')
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, WorkType> */
    private function workTypes(Organization $organization, string $term): Collection
    {
        return $organization->workTypes()
            ->where('name', 'like', $this->wildcard($term))
            ->with('organization')
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, OrganizationMembership> */
    private function members(Organization $organization, string $term): Collection
    {
        return $organization->memberships()
            ->whereHas('user', fn (Builder $query) => $query
                ->where('name', 'like', $this->wildcard($term))
                ->orWhere('email', 'like', $this->wildcard($term)))
            ->with(['user.organizationRoles' => fn ($query) => $query->wherePivot('organization_id', $organization->id)])
            ->limit(self::PER_TYPE)
            ->get()
            ->each(fn (OrganizationMembership $membership) => $membership->setRelation('organization', $organization));
    }

    /** @return Collection<int, Role> */
    private function roles(Organization $organization, string $term): Collection
    {
        return $organization->roles()
            ->where('name', 'like', $this->wildcard($term))
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get()
            ->each(fn (Role $role) => $role->setRelation('organization', $organization));
    }

    /** @return Collection<int, Organization> */
    private function organizations(User $user, string $term): Collection
    {
        return $user->organizations()
            ->where('name', 'like', $this->wildcard($term))
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @param  Builder<covariant Model>  $query */
    private function rank(Builder $query, string $column, string $term): void
    {
        $query->orderByRaw(
            $column === 'title' ? 'INSTR(LOWER(title), ?)' : 'INSTR(LOWER(name), ?)',
            [mb_strtolower($term)],
        )->orderBy($column);
    }

    private function wildcard(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
