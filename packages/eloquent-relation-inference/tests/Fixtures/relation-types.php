<?php

declare(strict_types=1);

namespace Quill\EloquentRelationInference\Tests\Fixtures;

use function PHPStan\Testing\assertType;

function belongsToIsParameterised(Post $post): void
{
    assertType(
        'Illuminate\Database\Eloquent\Relations\BelongsTo<Quill\EloquentRelationInference\Tests\Fixtures\Author, Quill\EloquentRelationInference\Tests\Fixtures\Post>',
        $post->author(),
    );
}

function belongsToChainYieldsRelatedModel(Post $post): void
{
    assertType('Quill\EloquentRelationInference\Tests\Fixtures\Author|null', $post->author()->first());
}

function hasOneIsParameterised(Post $post): void
{
    assertType(
        'Illuminate\Database\Eloquent\Relations\HasOne<Quill\EloquentRelationInference\Tests\Fixtures\Comment, Quill\EloquentRelationInference\Tests\Fixtures\Post>',
        $post->featuredComment(),
    );
}

function hasManyChainYieldsRelatedCollection(Post $post): void
{
    assertType(
        'Illuminate\Database\Eloquent\Collection<int, Quill\EloquentRelationInference\Tests\Fixtures\Comment>',
        $post->comments()->get(),
    );
}

/**
 * belongsToMany results carry the pivot, so the related model is intersected with it.
 */
function belongsToManyIsParameterised(Post $post): void
{
    assertType(
        '(object{pivot: Illuminate\Database\Eloquent\Relations\Pivot}&Quill\EloquentRelationInference\Tests\Fixtures\Tag)|null',
        $post->tags()->first(),
    );
}

function hasManyThroughUsesIntermediateModel(Post $post): void
{
    assertType(
        'Illuminate\Database\Eloquent\Relations\HasManyThrough<Quill\EloquentRelationInference\Tests\Fixtures\Author, Quill\EloquentRelationInference\Tests\Fixtures\Comment, Quill\EloquentRelationInference\Tests\Fixtures\Post>',
        $post->commenters(),
    );
}

function chainedBuilderCallStillResolves(Post $post): void
{
    assertType(
        'Illuminate\Database\Eloquent\Collection<int, Quill\EloquentRelationInference\Tests\Fixtures\Comment>',
        $post->publishedComments()->get(),
    );
}

function relationDeclaredInTraitResolves(Author $author): void
{
    assertType(
        'Illuminate\Database\Eloquent\Relations\HasMany<Quill\EloquentRelationInference\Tests\Fixtures\Post, Quill\EloquentRelationInference\Tests\Fixtures\Author>',
        $author->posts(),
    );
}

function morphToIsDeclined(Post $post): void
{
    assertType('Illuminate\Database\Eloquent\Relations\MorphTo', $post->subject());
}

function unresolvableRelatedClassIsDeclined(Post $post): void
{
    assertType('Illuminate\Database\Eloquent\Relations\HasMany', $post->dynamic());
}
