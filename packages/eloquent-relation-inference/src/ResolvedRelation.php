<?php

declare(strict_types=1);

namespace Quill\EloquentRelationInference;

final readonly class ResolvedRelation
{
    /**
     * @param class-string $relationClass the Eloquent relation, e.g. BelongsTo
     * @param class-string $relatedClass the model on the far side
     * @param class-string|null $intermediateClass set only for through-relations
     */
    public function __construct(
        public string $relationClass,
        public string $relatedClass,
        public ?string $intermediateClass = null,
    ) {}

    public function isThrough(): bool
    {
        return $this->intermediateClass !== null;
    }
}
