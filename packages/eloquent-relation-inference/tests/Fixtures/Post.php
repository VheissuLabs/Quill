<?php

declare(strict_types=1);

namespace Quill\EloquentRelationInference\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Post extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function featuredComment(): HasOne
    {
        return $this->hasOne(Comment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function commenters(): HasManyThrough
    {
        return $this->hasManyThrough(Author::class, Comment::class);
    }

    /** A chained builder call before the relation is still resolvable. */
    public function publishedComments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('published', true);
    }

    /** morphTo has no single related model, so the extension must decline it. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A computed class name cannot be read statically, so the extension must
     * decline and leave the declared type alone.
     */
    public function dynamic(): HasMany
    {
        $related = Comment::class;

        return $this->hasMany($related);
    }
}
