<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait GeneratesOrganizationScopedSlugs
{
    protected static function bootGeneratesOrganizationScopedSlugs(): void
    {
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = static::generateOrganizationScopedSlug($model->organization_id, $model->name);
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('name')) {
                $model->slug = static::generateOrganizationScopedSlug($model->organization_id, $model->name, $model->id);
            }
        });
    }

    protected static function generateOrganizationScopedSlug(int $organizationId, string $name, ?int $excludeId = null): string
    {
        $defaultSlug = Str::slug($name);

        $existingSlugs = static::query()
            ->where('organization_id', $organizationId)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->where(fn ($query) => $query
                ->where('slug', $defaultSlug)
                ->orWhere('slug', 'like', $defaultSlug.'-%'))
            ->pluck('slug');

        if ($existingSlugs->isEmpty()) {
            return $defaultSlug;
        }

        $maxSuffix = $existingSlugs
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

        return $defaultSlug.'-'.($maxSuffix + 1);
    }
}
