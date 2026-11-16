<?php

namespace App\Observers;

use App\Models\BillingSetting;
use Illuminate\Support\Str;

class BillingSettingObserver
{
    public function saving(BillingSetting $settings): void
    {
        $settings->invoice_prefix = strtoupper(trim($settings->invoice_prefix));
        $settings->square_webhook_token ??= Str::random(40);
    }
}
