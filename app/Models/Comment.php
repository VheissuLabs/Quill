<?php

namespace App\Models;

use App\Observers\CommentObserver;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperComment */
#[ObservedBy(CommentObserver::class)]
#[UseFactory(CommentFactory::class)]
class Comment extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Comment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /** @return HasMany<Comment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->oldest();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function url(): string
    {
        $commentable = $this->commentable;

        return match (true) {
            $commentable instanceof Issue => route('issues.show', [
                'current_organization' => $commentable->project->organization,
                'issue' => $commentable,
            ], absolute: false),
            $commentable instanceof Feature => route('portal.features.show', [
                'organization' => $commentable->project->organization,
                'project' => $commentable->project,
                'feature' => $commentable,
            ], absolute: false),
            default => throw new RuntimeException('Comment on an unsupported subject.'),
        };
    }

    public function subjectTitle(): string
    {
        $commentable = $this->commentable;

        if ($commentable instanceof Issue || $commentable instanceof Feature) {
            return $commentable->title;
        }

        throw new RuntimeException('Comment on an unsupported subject.');
    }

    public function organization(): Organization
    {
        $commentable = $this->commentable;

        if ($commentable instanceof Issue || $commentable instanceof Feature) {
            return $commentable->project->organization;
        }

        throw new RuntimeException('Comment on an unsupported subject.');
    }

    /** @return BelongsToMany<User, $this> */
    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_mentions')
            ->withTimestamps();
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function public(Builder $query): void
    {
        $query->where('is_internal', false);
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function threadsVisibleTo(Builder $query, bool $isMember): void
    {
        $query->topLevel()
            ->with([
                'author',
                'mentions',
                'commentable' => function (Relation $commentable): void {
                    if ($commentable instanceof MorphTo) {
                        $commentable->morphWith([
                            Feature::class => ['project.organization'],
                            Issue::class => ['project.organization'],
                        ]);
                    }
                },
                'replies' => fn ($query) => $query
                    ->with([
                        'author',
                        'mentions',
                        'commentable' => function (Relation $commentable): void {
                            if ($commentable instanceof MorphTo) {
                                $commentable->morphWith([
                                    Feature::class => ['project.organization'],
                                    Issue::class => ['project.organization'],
                                ]);
                            }
                        },
                    ])
                    ->when(! $isMember, fn ($query) => $query->public()),
            ])
            ->when(! $isMember, fn (Builder $query) => $query->public())
            ->oldest();
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function topLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('comments')
            ->logOnly(['commentable_type', 'commentable_id', 'parent_id', 'user_id', 'body', 'is_internal'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
