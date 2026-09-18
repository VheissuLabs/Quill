<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\OrganizationCapability;
use App\Enums\SprintStatus;
use App\Events\IssuesChanged;
use App\Observers\SprintObserver;
use Database\Factories\SprintFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property-read int|null $done_issues_count
 * @property-read int|float|string|null $issues_sum_points
 * @property-read Organization|Team $owner
 *
 * @mixin IdeHelperSprint
 */
#[UseFactory(SprintFactory::class)]
#[ObservedBy(SprintObserver::class)]
class Sprint extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function onBoardFor(Builder $query, Organization $organization, ?Team $team): void
    {
        $query->when(
            $team,
            fn (Builder $query, Team $team) => $query->whereMorphedTo('owner', $team),
            fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereMorphedTo('owner', $organization)
                ->orWhere(fn (Builder $query) => $query
                    ->where('owner_type', (new Team)->getMorphClass())
                    ->whereIn('owner_id', $organization->teams()->select('id')))),
        );
    }

    /** @return EloquentCollection<int, self> */
    public static function boardFor(Organization|Team $owner): EloquentCollection
    {
        return $owner->sprints()
            ->with('owner')
            ->withCount([
                'issues',
                'issues as done_issues_count' => fn (Builder $query) => $query->where('status', IssueStatus::Done),
            ])
            ->withSum('issues', 'points')
            ->orderByDesc('starts_at')
            ->get()
            ->filter(fn (self $sprint) => $sprint->runsSprints())
            ->values();
    }

    public function runsSprints(): bool
    {
        return $this->owner->canUse(OrganizationCapability::Sprints);
    }

    public function organization(): Organization
    {
        return $this->owner instanceof Team ? $this->owner->organization : $this->owner;
    }

    public function teamId(): ?int
    {
        return $this->owner instanceof Team ? $this->owner->getKey() : null;
    }

    /** @return Collection<int, BoardColumn> */
    public function columns(): Collection
    {
        return $this->owner->columns;
    }

    /**
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        if ($childType === 'column') {
            return $this->owner->columns()->where($field ?? (new BoardColumn)->getRouteKeyName(), $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
    }

    /** @return HasMany<Issue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function start(): void
    {
        if ($this->status !== SprintStatus::Planned) {
            throw new DomainException(__('Only a planned sprint can be started.'));
        }

        $this->update(['status' => SprintStatus::Active]);
    }

    /** @param  array<int, int>  $issueIds */
    private function announce(array $issueIds): void
    {
        if ($issueIds !== []) {
            broadcast(new IssuesChanged($this->organization()->getKey(), $issueIds))->toOthers();
        }
    }

    public function complete(): void
    {
        if ($this->status !== SprintStatus::Active) {
            throw new DomainException(__('Only an active sprint can be completed.'));
        }

        DB::transaction(function () {
            $returning = $this->issues()
                ->whereNotIn('status', [IssueStatus::Done, IssueStatus::Cancelled]);

            $returned = $returning->pluck('issues.id')->all();

            $returning->update(['sprint_id' => null, 'board_column_id' => null, 'status' => IssueStatus::Backlog]);

            $this->announce($returned);

            $this->forceFill([
                'status' => SprintStatus::Completed,
                'completed_at' => now(),
            ])->save();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sprints')
            ->logOnly(['name', 'status', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => SprintStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
