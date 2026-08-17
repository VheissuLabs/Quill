<?php

namespace App\Models;

use App\Observers\IssueReporterObserver;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperIssue */

#[ObservedBy(IssueReporterObserver::class)]
#[UseFactory(IssueFactory::class)]
class Issue extends Model
{
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'closed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('organization');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(IssueType::class, 'issue_type_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @param Builder<$this> $query
     * @return Builder<$this>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /**
     * @param Builder<$this> $query
     * @return Builder<$this>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('closed_at');
    }

    public function close(): void
    {
        $this->update(['closed_at' => now()]);
    }

    public function reopen(): void
    {
        $this->update(['closed_at' => null]);
    }

    protected static function boot(): void
    {
        parent::boot();

        /**
         * The number is allocated against a locked project row, so two issues
         * filed at the same moment cannot claim the same one. The unique index
         * is the backstop.
         */
        static::creating(function (Issue $issue) {
            if (array_key_exists('number', $issue->getAttributes())) {
                return;
            }

            $issue->number = DB::transaction(function () use ($issue) {
                Project::whereKey($issue->project_id)->lockForUpdate()->firstOrFail();

                return (int) Issue::withTrashed()
                    ->where('project_id', $issue->project_id)
                    ->max('number') + 1;
            });
        });
    }

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }
}
