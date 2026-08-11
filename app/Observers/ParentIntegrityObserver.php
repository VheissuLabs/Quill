<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use RuntimeException;

/**
 * `teams` and `clients` each carry a polymorphic `parent` that may be an
 * Organization or the other of the two. The database cannot express either
 * invariant this depends on, so both are checked on write:
 *
 * 1. A parent must belong to the same organization.
 * 2. The parent chain must stay a tree — no node may be its own ancestor.
 *
 * Parents are resolved from the model's current attributes rather than the
 * `parent` relation. Eloquent caches a loaded relation, so a model whose
 * `parent` was read during an earlier save would otherwise be validated
 * against the parent it used to have.
 */
class ParentIntegrityObserver
{
    public function saving(Team|Client $model): void
    {
        $parent = $this->resolveParent($model);

        if ($parent === null) {
            return;
        }

        $this->assertSameOrganization($model, $parent);
        $this->assertNoCycle($model, $parent);

        $model->unsetRelation('parent');
    }

    protected function resolveParent(Team|Client $model): Organization|Team|Client|null
    {
        $type = $model->parent_type;
        $id = $model->parent_id;

        if ($type === null || $id === null) {
            return null;
        }

        return match ($type) {
            Organization::class => Organization::find($id),
            Client::class => Client::find($id),
            Team::class => Team::find($id),
            default => throw new RuntimeException(sprintf('[%s] is not a valid parent type.', $type)),
        };
    }

    protected function assertSameOrganization(Team|Client $model, Organization|Team|Client $parent): void
    {
        $parentOrganizationId = $parent instanceof Organization
            ? $parent->id
            : $parent->organization_id;

        if ($model->organization_id !== $parentOrganizationId) {
            throw new RuntimeException(sprintf(
                '%s cannot be held by a parent in another organization.',
                class_basename($model),
            ));
        }
    }

    protected function assertNoCycle(Team|Client $model, Organization|Team|Client $parent): void
    {
        if (! $model->exists) {
            return;
        }

        $ancestor = $parent;
        $seen = [];

        while ($ancestor instanceof Team || $ancestor instanceof Client) {
            if ($ancestor::class === $model::class && $ancestor->id === $model->id) {
                throw new RuntimeException(sprintf(
                    '%s cannot be held by its own descendant.',
                    class_basename($model),
                ));
            }

            $key = $ancestor::class.':'.$ancestor->id;

            if (isset($seen[$key])) {
                throw new RuntimeException('The parent chain already contains a cycle.');
            }

            $seen[$key] = true;
            $ancestor = $this->resolveParent($ancestor);
        }
    }
}
