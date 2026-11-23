<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Enums\IssueStatus;
use App\Events\TeamsChanged;
use App\Observers\BoardColumnObserver;
use Database\Factories\BoardColumnFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property-read Organization|Team $owner
 *
 * @mixin IdeHelperBoardColumn
 */
#[ObservedBy(BoardColumnObserver::class)]
#[UseFactory(BoardColumnFactory::class)]
class BoardColumn extends Model
{
    use BroadcastsChanges, HasFactory, LogsActivity;

    protected $guarded = [];

    /** @var list<string> */
    protected $with = ['owner'];

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function organization(): Organization
    {
        return $this->owner instanceof Team
            ? $this->owner->organization
            : $this->owner;
    }

    public function teamId(): ?int
    {
        return $this->owner instanceof Team
            ? $this->owner->getKey()
            : null;
    }

    /** @return HasMany<Issue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function deleteAndReassignIssues(): self
    {
        $columns = $this->owner->columns()->get();

        if ($columns->count() === 1) {
            throw new DomainException(__('A board needs at least one column.'));
        }

        $index = $columns->search(fn (self $candidate) => $candidate->is($this));
        $fallback = $columns[$index - 1] ?? $columns[$index + 1];

        DB::transaction(function () use ($fallback) {
            $this->issues()->each(fn (Issue $issue) => $issue->moveToColumn($fallback));

            $this->delete();
        });

        return $fallback;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('board_columns')
            ->logOnly(['name', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => IssueStatus::class,
        ];
    }

    public function changeBroadcast(): TeamsChanged
    {
        $this->loadMissing('owner');

        return new TeamsChanged($this->owner instanceof Team ? $this->owner->organization_id : $this->owner->id);
    }
}
