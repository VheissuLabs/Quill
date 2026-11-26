<?php

namespace App\Observers;

use App\Models\Address;

class AddressObserver
{
    public function saving(Address $address): void
    {
        $address->line2 = filled($address->line2) ? $address->line2 : null;
    }
}
