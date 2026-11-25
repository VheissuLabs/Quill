<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Enums\VoteDirection;
use App\Events\FeedbackChanged;
use App\Events\FeedbackVoted;
use App\Observers\FeedbackObserver;
use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property-read int|null $downvotes_count
 * @property-read int|null $score
 * @property-read int|null $user_vote
 *
 * @mixin IdeHelperFeedback
 */
#[ObservedBy(FeedbackObserver::class)]
#[UseFactory(FeedbackFactory::class)]
class Feedback extends Model
{
    use BroadcastsChanges, HasFactory, LogsActivity;

    protected $table = 'feedback';

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

    /** @param  array<string, mixed>  $attributes */
    public static function submit(Project $project, User $author, array $attributes): self
    {
        $feedback = $project->feedback()->create([
            ...$attributes,
            'user_id' => $author->id,
            'status' => FeedbackStatus::Open,
        ]);

        $feedback->castVote($author, VoteDirection::Up);

        return $feedback;
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', FeedbackStatus::active());
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function withSummary(Builder $query, ?User $viewer): void
    {
        $query
            ->withVoteSummary($viewer)
            ->withCount(['comments' => fn ($query) => $query->public()])
            ->with(['author', 'issue.project']);
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function inOrganization(Builder $query, Organization $organization): void
    {
        $query->whereIn('project_id', $organization->projects()->select('projects.id'));
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function onBoard(Builder $query, ?FeedbackStatus $status, ?FeedbackType $type, ?string $search, string $sort): void
    {
        $query
            ->when($status, fn (Builder $query) => $query->where('status', $status), fn (Builder $query) => $query->active())
            ->when($type, fn (Builder $query) => $query->where('type', $type))
            ->when($search, fn (Builder $query, string $term) => $query->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($sort === 'top', fn (Builder $query) => $query->orderByRaw('coalesce(score, 0) desc'))
            ->latest()
            ->orderByDesc('id');
    }

    /** @param  Builder<$this>  $query */
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
                    ->whereColumn('feedback_id', 'feedback.id')
                    ->where('user_id', $viewer->id)
                    ->limit(1),
            ]));
    }

    public function castVote(User $user, VoteDirection $direction): void
    {
        $this->votes()->updateOrCreate(['user_id' => $user->id], ['direction' => $direction->weight()]);

        broadcast(new FeedbackVoted($this->project_id, $this->getKey()))->toOthers();
    }

    public function clearVoteFrom(User $user): void
    {
        $this->votes()->where('user_id', $user->getKey())->delete();

        broadcast(new FeedbackVoted($this->project_id, $this->getKey()))->toOthers();
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
            ->useLogName('feedback')
            ->logOnly(['title', 'description', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => FeedbackStatus::class,
            'type' => FeedbackType::class,
        ];
    }

    public function changeBroadcast(): FeedbackChanged
    {
        return new FeedbackChanged($this->project_id, $this->getKey());
    }
}
