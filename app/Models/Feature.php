<?php

namespace App\Models;

use App\Enums\FeatureStatus;
use App\Enums\VoteDirection;
use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property-read int|null $downvotes_count
 * @property-read int|null $score
 * @property-read int|null $user_vote
 *
 * @mixin IdeHelperFeature
 */
#[UseFactory(FeatureFactory::class)]
class Feature extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @param  Builder<$this>  $query */
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

    public function castVote(User $user, VoteDirection $direction): void
    {
        $this->votes()->updateOrCreate(['user_id' => $user->id], ['direction' => $direction->weight()]);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return Collection<int, User> */
    public function mentionableUsers(): Collection
    {
        return $this->project->organization->members()->get()
            ->when($this->author, fn ($users) => $users->push($this->author))
            ->merge(User::whereIn('id', $this->comments()->whereNotNull('user_id')->select('user_id'))->get())
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @return HasOne<Issue, $this> */
    public function issue(): HasOne
    {
        return $this->hasOne(Issue::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('features')
            ->logOnly(['title', 'description', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => FeatureStatus::class,
        ];
    }
}
