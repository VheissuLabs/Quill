<?php

namespace App\Observers;

use App\Enums\SquareCheckoutStatus;
use App\Models\SquareCheckout;

class SquareCheckoutObserver
{
    public function creating(SquareCheckout $checkout): void
    {
        $checkout->status ??= SquareCheckoutStatus::Open;
    }
}
