<?php

namespace App\Models;

use App\Concerns\HasDocuments;
use App\Enums\IssueLinkType;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Observers\IssueObserver;
use Database\Factories\IssueFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperIssue */
#[ObservedBy(IssueObserver::class)]
#[UseFactory(IssueFactory::class)]
class Issue extends Model
{
    use HasDocuments, HasFactory, LogsActivity;

    protected $guarded = [];

    protected $attributes = ['priority' => IssuePriority::None->value];

    /** @return BelongsTo<Project, $this> */
    /**
     * Issues the viewer can see, for picking one by name when only its id can be typed.
     *
     * @param  Collection<int, int>|null  $clientIds
     * @return EloquentCollection<int, self>
     */
    public static function matching(Organization $organization, User $user, string $term, ?int $except = null, ?Collection $clientIds = null): EloquentCollection
    {
        if (mb_strlen($term) < 2) {
            return new EloquentCollection;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return self::query()
            ->whereIn('project_id', $organization->projects()->visibleTo($user)->select('projects.id'))
            ->when($clientIds, fn (Builder $query, Collection $ids) => $query->whereIn('project_id', Project::query()->whereIn('client_id', $ids)->select('id')))
            ->when($except, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', $like)
                ->when(ctype_digit($term), fn (Builder $query) => $query->orWhere('number', (int) $term)))
            ->with(['project', 'boardColumn'])
            ->orderByRaw('INSTR(LOWER(title), ?)', [mb_strtolower($term)])
            ->orderBy('title')
            ->limit(8)
            ->get();
    }

    /** @return HasMany<IssueLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(IssueLink::class);
    }

    /** @return HasMany<IssueLink, $this> */
    public function inverseLinks(): HasMany
    {
        return $this->hasMany(IssueLink::class, 'related_issue_id');
    }

    /**
     * Both directions read from this issue's point of view: a row saying it blocks another
     * shows as "Blocks", the same row on the other issue shows as "Blocked by".
     *
     * @return Collection<int, array{id: int, type: string, label: string, inverse: bool, issue: Issue}>
     */
    public function relatedIssues(): Collection
    {
        $this->loadMissing(['links.relatedIssue.project', 'inverseLinks.issue.project']);

        return $this->links
            ->map(fn (IssueLink $link) => [
                'id' => $link->id,
                'type' => $link->type->value,
                'label' => $link->type->label(),
                'inverse' => false,
                'issue' => $link->relatedIssue,
            ])
            ->concat($this->inverseLinks
                ->map(fn (IssueLink $link) => [
                    'id' => $link->id,
                    'type' => $link->type->value,
                    'label' => $link->type->inverseLabel(),
                    'inverse' => true,
                    'issue' => $link->issue,
                ]))
            ->sortBy('label')
            ->values();
    }

    public function linkTo(self $other, IssueLinkType $type): IssueLink
    {
        if ($other->is($this)) {
            throw new DomainException(__('An issue cannot be linked to itself.'));
        }

        if (! $other->project->organization->is($this->project->organization)) {
            throw new DomainException(__('That issue belongs to another organization.'));
        }

        return IssueLink::firstOrCreate([
            'issue_id' => $this->getKey(),
            'related_issue_id' => $other->getKey(),
            'type' => $type,
        ]);
    }

    /** Rewrites a link so it reads the given way round from this issue. */
    public function recastLink(IssueLink $link, IssueLinkType $type, bool $inverse): IssueLink
    {
        $other = $link->issue_id === $this->getKey() ? $link->relatedIssue : $link->issue;

        $link->delete();

        return $inverse
            ? $other->linkTo($this, $type)
            : $this->linkTo($other, $type);
    }

    public function unlinkFrom(self $other): int
    {
        return IssueLink::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('issue_id', $this->getKey())->where('related_issue_id', $other->getKey()))
                ->orWhere(fn (Builder $query) => $query->where('issue_id', $other->getKey())->where('related_issue_id', $this->getKey())))
            ->delete();
    }

    /** @param  array<int, int>  $ids */
    public static function reorder(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $cases = implode(' ', array_fill(0, count($ids), 'when ? then ?'));
        $slots = implode(',', array_fill(0, count($ids), '?'));

        $bindings = collect($ids)
            ->flatMap(fn (int $id, int $position) => [$id, $position])
            ->concat($ids)
            ->all();

        DB::update(
            "update issues set position = case id {$cases} end where id in ({$slots})",
            $bindings
        );
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** @return Collection<int, User> */
    public function mentionableUsers(): Collection
    {
        return $this->project->organization->members()->get()
            ->merge(User::whereIn('id', $this->comments()->whereNotNull('user_id')->select('user_id'))->get())
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Sprint, $this> */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /** @return BelongsTo<BoardColumn, $this> */
    public function boardColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class);
    }

    /** @return BelongsTo<Feedback, $this> */
    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function ownedBy(Builder $query, ?Team $team): void
    {
        $query->when($team, fn (Builder $query, Team $team) => $query
            ->where(fn (Builder $query) => $query->where('team_id', $team->id)->orWhereNull('team_id')));
    }

    /**
     * @param  Builder<$this>  $query
     * @param  Collection<int, int>  $projectIds
     * @param  array{status: string|null, project: string|null, assignee: string|null, search: string|null}  $filters
     */
    #[Scope]
    protected function onOrganizationBoard(Builder $query, Collection $projectIds, User $user, ?Team $team, array $filters): void
    {
        $query->whereIn('project_id', $projectIds)
            ->with(['project', 'assignee', 'sprint', 'team.columns', 'boardColumn'])
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(! $filters['status'], fn (Builder $query) => $query->whereNotIn('status', [IssueStatus::Done, IssueStatus::Cancelled]))
            ->when($filters['project'], fn (Builder $query, string $slug) => $query->whereHas('project', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['assignee'] === 'me', fn (Builder $query) => $query->where('assignee_id', $user->id))
            ->when($filters['search'], fn (Builder $query, string $term) => $query->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->ownedBy($team)
            ->whereNull('sprint_id')
            ->orderBy('position')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->latest();
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{project?: string|null, status?: string|null, search?: string|null}  $filters
     */
    #[Scope]
    protected function searchedFor(Builder $query, array $filters): void
    {
        $query
            ->when($filters['project'] ?? null, fn (Builder $query, string $project) => $query->whereHas('project', fn (Builder $query) => $query->identifiedBy($project)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('title', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->when(ctype_digit($term), fn (Builder $query) => $query->orWhere('number', (int) $term))));
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return EloquentCollection<int, Activity>
     */
    public static function recentActivity(Collection $projectIds, ?Team $team, int $limit = 20): EloquentCollection
    {
        return Activity::query()
            ->where('log_name', 'issues')
            ->where('subject_type', (new self)->getMorphClass())
            ->whereIn('subject_id', self::whereIn('project_id', $projectIds)->ownedBy($team)->select('id'))
            ->with(['causer', 'subject.project'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function reference(): string
    {
        $this->loadMissing('project');

        return "{$this->project->name} #{$this->number}";
    }

    public function planInto(?Sprint $sprint): void
    {
        if (! $sprint) {
            $this->update([
                'sprint_id' => null,
                'board_column_id' => null,
                'status' => $this->status->isClosed() ? $this->status : IssueStatus::Backlog,
            ]);

            return;
        }

        $columns = $sprint->columns();

        $column = $columns->firstWhere('status', $this->status)
            ?? $columns->firstWhere('status', IssueStatus::Todo)
            ?? $columns->first();

        $this->update(['sprint_id' => $sprint->id, 'team_id' => $sprint->teamId()]);

        if ($column) {
            $this->moveToColumn($column);
        }
    }

    public function moveToColumn(BoardColumn $column): void
    {
        $this->update(['board_column_id' => $column->id, 'team_id' => $column->teamId()]);

        $this->changeStatus($column->status);
    }

    public function changeStatus(IssueStatus $status): void
    {
        $this->loadMissing('feedback');

        DB::transaction(function () use ($status) {
            $leavesBoard = in_array($status, [
                IssueStatus::Backlog,
                IssueStatus::Cancelled],
                true
            );

            $this->forceFill([
                'status' => $status,
                'board_column_id' => $leavesBoard ? null : $this->board_column_id,
                'sprint_id' => $status === IssueStatus::Cancelled ? null : $this->sprint_id,
                'completed_at' => $status === IssueStatus::Done ? ($this->completed_at ?? now()) : null,
            ])->save();

            if ($this->feedback && ($feedbackStatus = $status->feedbackStatus())) {
                $this->feedback->update(['status' => $feedbackStatus]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('issues')
            ->logOnly([
                'title',
                'description',
                'status',
                'priority',
                'points',
                'sprint_id',
                'board_column_id',
                'assignee_id',
                'team_id',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => IssueStatus::class,
            'priority' => IssuePriority::class,
            'completed_at' => 'datetime',
        ];
    }
}
