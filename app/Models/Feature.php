<?php

namespace App\Models;

use App\Enums\FeatureStatus;
use App\Enums\VoteDirection;
use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A feature request submitted to a project's board.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $user_id
 * @property string $title
 * @property string|null $description
 * @property FeatureStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $votes_count
 * @property-read int|null $downvotes_count
 * @property-read int|null $score
 * @property-read int|null $user_vote
 * @property-read int|null $comments_count
 * @property-read Project $project
 * @property-read User|null $author
 * @property-read Collection<int, Vote> $votes
 * @property-read Collection<int, Comment> $comments
 * @property-read Issue|null $issue
 */
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /**
     * Get the project the feature was requested for.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who requested the feature.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Add the vote counts, the net score, and the viewer's own vote direction to the query.
     *
     * @param  Builder<$this>  $query
     */
    /**
     * Only requests that are still in play: completed and declined ones leave the board.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', FeatureStatus::active());
    }

    #[Scope]
    protected function withVoteSummary(Builder $query, ?User $viewer): void
    {
        $query
            ->withCount([
                'votes as votes_count' => fn (Builder $query) => $query->where('direction', 1),
                'votes as downvotes_count' => fn (Builder $query) => $query->where('direction', -1),
            ])
            ->withSum('votes as score', 'direction')
            ->when($viewer, fn (Builder $query) => $query->addSelect([
                'user_vote' => Vote::select('direction')
                    ->whereColumn('feature_id', 'features.id')
                    ->where('user_id', $viewer->id)
                    ->limit(1),
            ]));
    }

    /**
     * Record, switch or keep the user's vote in the given direction.
     */
    public function castVote(User $user, VoteDirection $direction): void
    {
        $this->votes()->updateOrCreate(['user_id' => $user->id], ['direction' => $direction->weight()]);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the people who can be @mentioned on this feature: members of the organization, the
     * requester, and anyone who has commented.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function mentionableUsers(): \Illuminate\Support\Collection
    {
        return $this->project->organization->members()->get()
            ->when($this->author, fn ($users) => $users->push($this->author))
            ->merge(User::whereIn('id', $this->comments()->whereNotNull('user_id')->select('user_id'))->get())
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Get the issue the feature was converted into, if any.
     *
     * @return HasOne<Issue, $this>
     */
    public function issue(): HasOne
    {
        return $this->hasOne(Issue::class);
    }

    /**
     * Record who changed the request, and how, so a status change can always be traced.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('features')
            ->logOnly(['title', 'description', 'status'])
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
            'status' => FeatureStatus::class,
        ];
    }
}
