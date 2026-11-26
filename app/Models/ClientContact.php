<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Concerns\HasAddresses;
use App\Events\ClientsChanged;
use App\Observers\ClientContactObserver;
use Database\Factories\ClientContactFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperClientContact */
#[ObservedBy(ClientContactObserver::class)]
#[UseFactory(ClientContactFactory::class)]
class ClientContact extends Model
{
    use BroadcastsChanges, HasAddresses, HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function displayName(): string
    {
        return $this->name ?? $this->email;
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like))
            ->with('client')
            ->orderBy('name')
            ->limit(8);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('clients')
            ->logOnly(['client_id', 'name', 'title', 'email', 'phone'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function changeBroadcast(): ClientsChanged
    {
        return new ClientsChanged($this->organization_id);
    }
}
