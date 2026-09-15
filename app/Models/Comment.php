<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A comment on a feature request. Comments form one level of threads: a reply points at the
 * top-level comment it belongs to. Internal comments are only shown to members of the
 * organization that owns the project.
 *
 * @property int $id
 * @property int $feature_id
 * @property int|null $parent_id
 * @property int|null $user_id
 * @property string $body
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Feature $feature
 * @property-read Comment|null $parent
 * @property-read Collection<int, Comment> $replies
 * @property-read User|null $author
 * @property-read Collection<int, User> $mentions
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
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
            ->logOnly(['feature_id', 'parent_id', 'user_id', 'body', 'is_internal'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
