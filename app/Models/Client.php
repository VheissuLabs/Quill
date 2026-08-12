<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueSlugs;
use App\Observers\ParentIntegrityObserver;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperClient */

#[ObservedBy(ParentIntegrityObserver::class)]
#[UseFactory(ClientFactory::class)]
class Client extends Model
{
    use GeneratesUniqueSlugs, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('organization');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return MorphTo<Model, $this> */
    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function teams(): MorphMany
    {
        return $this->morphMany(Team::class, 'parent');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Client $client) {
            if (empty($client->slug)) {
                $client->slug = static::generateUniqueSlug($client->name);
            }
        });

        static::updating(function (Client $client) {
            if ($client->isDirty('name')) {
                $client->slug = static::generateUniqueSlug($client->name, $client->id);
            }
        });
    }
}
