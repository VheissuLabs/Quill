<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Enums\EmailDirection;
use App\Enums\IssueStatus;
use App\Enums\OrganizationPermission;
use App\Enums\TicketStatus;
use App\Events\TicketsChanged;
use App\Observers\TicketObserver;
use Database\Factories\TicketFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperTicket */
#[ObservedBy(TicketObserver::class)]
#[UseFactory(TicketFactory::class)]
class Ticket extends Model
{
    use BroadcastsChanges, HasFactory, LogsActivity;

    public const INTERNAL_FILTER = 'internal';

    protected $guarded = [];

    public static function inboundAddress(): string
    {
        return (string) (config('services.resend.support_inbound_address') ?: config('services.resend.support_address'));
    }

    public static function addressedBy(string $address): ?int
    {
        $local = Str::before(self::inboundAddress(), '@');
        $domain = Str::after(self::inboundAddress(), '@');

        return preg_match('/^'.preg_quote($local, '/').'\+t(\d+)@'.preg_quote($domain, '/').'$/i', trim($address), $matches)
            ? (int) $matches[1]
            : null;
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /** @return BelongsTo<ClientContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id');
    }

    /** @return BelongsTo<Issue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** @return Collection<int, User> */
    public function mentionableUsers(): Collection
    {
        return $this->organization->members()
            ->orderBy('name')
            ->get();
    }

    public function reference(): string
    {
        return "T-{$this->number}";
    }

    public function replyAddress(): string
    {
        return Str::before(self::inboundAddress(), '@')
            .'+t'.$this->getKey()
            .'@'.Str::after(self::inboundAddress(), '@');
    }

    public function latestInboundMessage(): ?EmailMessage
    {
        return EmailMessage::query()
            ->where('direction', EmailDirection::Inbound)
            ->whereHas('comment', fn (Builder $query) => $query->whereMorphedTo('commentable', $this))
            ->latest('id')
            ->first();
    }

    /** @return array<int, string> */
    public function threadMessageIds(): array
    {
        return EmailMessage::query()
            ->whereHas('comment', fn (Builder $query) => $query->whereMorphedTo('commentable', $this))
            ->oldest('id')
            ->pluck('message_id')
            ->all();
    }

    /** @return Collection<int, User> */
    public function watchers(): Collection
    {
        return $this->reporter
            ? new Collection([$this->reporter])
            : $this->organization->members()
                ->whereHas('organizationRoles', fn (Builder $query) => $query
                    ->where('model_has_roles.organization_id', $this->organization_id)
                    ->whereHas('permissions', fn (Builder $query) => $query->where('name', OrganizationPermission::ManageTickets->value)))
                ->get();
    }

    public function assignTo(?Client $client): void
    {
        if ($client && $client->organization_id !== $this->organization_id) {
            throw new DomainException(__('That client belongs to another organization.'));
        }

        $this->update(['client_id' => $client?->getKey(), 'is_internal' => false]);
    }

    public function assignToOrganization(): void
    {
        $this->update(['client_id' => null, 'is_internal' => true]);
    }

    public function isAssigned(): bool
    {
        return $this->client_id !== null || $this->is_internal;
    }

    /** @return Collection<int, Project> */
    public function projectsForIssues(): Collection
    {
        return match (true) {
            $this->is_internal => $this->organization->projects()->whereNull('client_id')->orderBy('name')->get(),
            $this->client_id !== null => $this->client->projects()->orderBy('name')->get(),
            default => new Collection,
        };
    }

    public function linkTo(Issue $issue, User $reporter): void
    {
        if (! $this->isAssigned()) {
            throw new DomainException(__('Assign this ticket to a client or to :organization first.', ['organization' => $this->organization->name]));
        }

        $issue->loadMissing('project');

        if ($issue->project->organization_id !== $this->organization_id) {
            throw new DomainException(__('That issue belongs to another organization.'));
        }

        $this->update([
            'issue_id' => $issue->getKey(),
            'reporter_id' => $reporter->getKey(),
            'issue_resolved_at' => $issue->status === IssueStatus::Done && $this->status->isActive() ? now() : null,
        ]);
    }

    public function unlink(): void
    {
        $this->update(['issue_id' => null, 'issue_resolved_at' => null]);
    }

    /** @param  array{status?: string, client_id?: int|null, internal?: bool}  $changes */
    public function applyChanges(array $changes): void
    {
        if ($changes['internal'] ?? false) {
            $this->assignToOrganization();
        } elseif (array_key_exists('client_id', $changes)) {
            $this->assignTo($changes['client_id'] !== null ? $this->organization->clients()->find($changes['client_id']) : null);
        }

        if (array_key_exists('status', $changes)) {
            $this->changeStatus(TicketStatus::from($changes['status']));
        }
    }

    public function changeStatus(TicketStatus $status): void
    {
        $this->update([
            'status' => $status,
            'issue_resolved_at' => $status->isActive() ? $this->issue_resolved_at : null,
        ]);
    }

    public function recordCustomerMessage(): void
    {
        $this->update([
            'status' => TicketStatus::Open,
            'last_message_at' => now(),
        ]);
    }

    public function recordTeamReply(): void
    {
        $this->update([
            'status' => TicketStatus::AwaitingCustomer,
            'issue_resolved_at' => null,
            'last_message_at' => now(),
        ]);
    }

    /** @param  array<int, int>  $ids */
    public static function deleteMany(Organization $organization, array $ids): int
    {
        $tickets = $organization->tickets()->whereKey($ids)->get();

        static::withoutBroadcastingChanges(fn () => $tickets->each(fn (Ticket $ticket) => $ticket->delete()));

        if ($tickets->isNotEmpty()) {
            broadcast(new TicketsChanged($organization->id))->toOthers();
        }

        return $tickets->count();
    }

    /**
     * @param  array{status: string|null, client: string|null, view: string|null}  $filters
     * @return array<string, int>
     */
    public static function countsByView(Organization $organization, User $viewer, array $filters): array
    {
        return collect(['inbox' => null, 'unassigned' => 'unassigned', 'ready' => 'ready', 'mine' => 'mine'])
            ->map(fn (?string $view) => $organization->tickets()->inInbox($viewer, [...$filters, 'view' => $view])->count())
            ->all();
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{status: string|null, client: string|null, view: string|null}  $filters
     */
    #[Scope]
    protected function inInbox(Builder $query, User $viewer, array $filters): void
    {
        $query->with(['client', 'issue.project', 'reporter'])
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(! $filters['status'], fn (Builder $query) => $query->whereIn('status', [TicketStatus::Open, TicketStatus::AwaitingCustomer]))
            ->when($filters['client'] === self::INTERNAL_FILTER, fn (Builder $query) => $query->where('is_internal', true))
            ->when($filters['client'] && $filters['client'] !== self::INTERNAL_FILTER, fn (Builder $query) => $query->whereHas('client', fn (Builder $query) => $query->where('slug', $filters['client'])))
            ->when($filters['view'] === 'unassigned', fn (Builder $query) => $query->whereNull('client_id')->where('is_internal', false))
            ->when($filters['view'] === 'ready', fn (Builder $query) => $query->whereNotNull('issue_resolved_at'))
            ->when($filters['view'] === 'mine', fn (Builder $query) => $query->where('reporter_id', $viewer->getKey()))
            ->latest('last_message_at')
            ->latest('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tickets')
            ->logOnly(['subject', 'status', 'client_id', 'is_internal', 'issue_id', 'reporter_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'is_internal' => 'boolean',
            'issue_resolved_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function changeBroadcast(): TicketsChanged
    {
        return new TicketsChanged($this->organization_id);
    }
}
