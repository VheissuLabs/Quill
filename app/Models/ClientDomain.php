<?php

namespace App\Models;

use App\Observers\ClientDomainObserver;
use Database\Factories\ClientDomainFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin IdeHelperClientDomain */
#[ObservedBy(ClientDomainObserver::class)]
#[UseFactory(ClientDomainFactory::class)]
class ClientDomain extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function clientFor(Organization $organization, string $email): ?Client
    {
        return self::query()
            ->where('organization_id', $organization->getKey())
            ->where('domain', strtolower(substr(strrchr($email, '@') ?: '', 1)))
            ->first()
            ?->client;
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
