<?php

declare(strict_types=1);

namespace Quill\EloquentRelationInference\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Exercises relation methods declared in a trait rather than the model. */
trait HasPosts
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
