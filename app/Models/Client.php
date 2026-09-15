<?php

namespace App\Models;

use App\Concerns\GeneratesOrganizationScopedSlugs;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A customer of an organization whose projects can be tracked separately.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, Project> $projects
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use GeneratesOrganizationScopedSlugs, HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('clients')
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
