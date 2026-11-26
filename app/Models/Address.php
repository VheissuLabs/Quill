<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Enums\AddressType;
use App\Events\BillingChanged;
use App\Events\ClientsChanged;
use App\Observers\AddressObserver;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** @mixin IdeHelperAddress */
#[UseFactory(AddressFactory::class)]
#[ObservedBy(AddressObserver::class)]
class Address extends Model
{
    use BroadcastsChanges, HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return list<string> */
    public function lines(): array
    {
        $locality = trim(implode(' ', array_filter([
            implode(', ', array_filter([$this->city, $this->region])),
            $this->postal_code,
        ])));

        return array_values(array_filter([$this->line1, $this->line2, $locality, $this->country], fn (?string $line) => filled($line)));
    }

    public function changeBroadcast(): BillingChanged|ClientsChanged
    {
        $owner = $this->addressable()->withoutGlobalScopes()->first();

        return $owner instanceof Organization
            ? new BillingChanged($owner->id)
            : new ClientsChanged((int) $owner?->getAttribute('organization_id'));
    }
}
