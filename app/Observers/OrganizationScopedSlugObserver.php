<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrganizationScopedSlugObserver
{
    public function creating(Client|Project|Team $model): void
    {
        $model->slug = $this->slugFor($model, $model->name);
    }

    public function updating(Client|Project|Team $model): void
    {
        if ($model->isDirty('name')) {
            $model->slug = $this->slugFor($model, $model->name, $model->getKey());
        }
    }

    private function slugFor(Client|Project|Team $model, string $name, ?int $excludeId = null): string
    {
        $defaultSlug = Str::slug($name);

        $existingSlugs = $model->newQuery()
            ->where('organization_id', $model->organization_id)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->where(fn ($query) => $query
                ->where('slug', $defaultSlug)
                ->orWhere('slug', 'like', $defaultSlug.'-%'))
            ->pluck('slug');

        if ($existingSlugs->isEmpty()) {
            return $defaultSlug;
        }

        return $defaultSlug.'-'.($this->highestSuffix($existingSlugs, $defaultSlug) + 1);
    }

    /** @param  Collection<int, string>  $slugs */
    private function highestSuffix($slugs, string $defaultSlug): int
    {
        return $slugs
            ->map(function (string $slug) use ($defaultSlug): ?int {
                if ($slug === $defaultSlug) {
                    return 0;
                }

                if (! preg_match('/^'.preg_quote($defaultSlug, '/').'-(\d+)$/', $slug, $matches)) {
                    return null;
                }

                return (int) $matches[1];
            })
            ->filter(fn (?int $suffix) => $suffix !== null)
            ->max() ?? 0;
    }
}
