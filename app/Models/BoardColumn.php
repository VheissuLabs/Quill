<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Observers\BoardColumnObserver;
use Database\Factories\BoardColumnFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
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

    /** @return HasMany<Issue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
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
}
