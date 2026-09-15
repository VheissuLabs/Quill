<?php

namespace App\Models;

use App\Enums\IssueStatus;
use Database\Factories\BoardColumnFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A column on a board. Its status is what an issue becomes when it is placed there.
 *
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $name
 * @property IssueStatus $status
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $issues_count
 * @property-read Organization|Team $owner
 * @property-read Collection<int, Issue> $issues
 */
class BoardColumn extends Model
{
    /** @use HasFactory<BoardColumnFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /**
     * Get whoever runs the board the column is on: a team, or the organization itself.
     *
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the organization the board belongs to, whether or not a team runs it.
     */
    public function organization(): Organization
    {
        return $this->owner instanceof Team ? $this->owner->organization : $this->owner;
    }

    /**
     * Get the team working off this board, or null when the organization runs it itself.
     */
    public function teamId(): ?int
    {
        return $this->owner instanceof Team ? $this->owner->getKey() : null;
    }

    /**
     * Get the issues currently in the column.
     *
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Record changes to a board's columns.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('board_columns')
            ->logOnly(['name', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IssueStatus::class,
        ];
    }
}
