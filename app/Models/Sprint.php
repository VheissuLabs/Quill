<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\SprintStatus;
use App\Observers\SprintObserver;
use Database\Factories\SprintFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function organization(): Organization
    {
        return $this->owner instanceof Team ? $this->owner->organization : $this->owner;
    }

    public function teamId(): ?int
    {
        return $this->owner instanceof Team ? $this->owner->getKey() : null;
    }

    /**
     * @return Collection<int, BoardColumn>
     */
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

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function start(): void
    {
        $this->update(['status' => SprintStatus::Active]);
    }

    public function complete(): void
    {
        DB::transaction(function () {
            $this->issues()
                ->whereNotIn('status', [IssueStatus::Done, IssueStatus::Cancelled])
                ->update(['sprint_id' => null, 'board_column_id' => null, 'status' => IssueStatus::Backlog]);

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

    /**
     * @return array<string, string>
     */
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
