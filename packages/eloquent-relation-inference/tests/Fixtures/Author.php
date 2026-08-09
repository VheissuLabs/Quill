<?php

declare(strict_types=1);

namespace Quill\EloquentRelationInference\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    use HasPosts;

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
