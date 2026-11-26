<?php

namespace App\Concerns;

use App\Enums\AddressType;
use App\Models\Address;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAddresses
{
    /** @return MorphMany<Address, $this> */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable')->orderBy('id');
    }

    public function billingAddress(): ?Address
    {
        $addresses = $this->relationLoaded('addresses') ? $this->addresses : $this->addresses()->get();

        return $addresses->first(fn (Address $address) => $address->type === AddressType::Billing) ?? $addresses->first();
    }
}
