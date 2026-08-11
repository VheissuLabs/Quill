<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueSlugs;
use App\Enums\OrganizationRole;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @mixin IdeHelperOrganization */

#[UseFactory(OrganizationFactory::class)]
class Organization extends Model
{
    use GeneratesUniqueSlugs, HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->first();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id', 'user_id')
            ->using(OrganizationMembership::class)
            ->withPivot(['role', 'client_id'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * Every client in the organization, at any depth. Distinct from
     * `childClients()`, which is only those the organization holds directly.
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function childClients(): MorphMany
    {
        return $this->morphMany(Client::class, 'parent');
    }

    /** Every team in the organization, at any depth. */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function childTeams(): MorphMany
    {
        return $this->morphMany(Team::class, 'parent');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = static::generateUniqueSlug($organization->name);
            }
        });

        static::updating(function (Organization $organization) {
            if ($organization->isDirty('name')) {
                $organization->slug = static::generateUniqueSlug($organization->name, $organization->id);
            }
        });
    }
}
