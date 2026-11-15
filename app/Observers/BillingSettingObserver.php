<?php

namespace App\Observers;

use App\Models\BillingSetting;

class BillingSettingObserver
{
    public function saving(BillingSetting $settings): void
    {
        $settings->invoice_prefix = strtoupper(trim($settings->invoice_prefix));
    }
}
