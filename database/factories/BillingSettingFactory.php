<?php

namespace Database\Factories;

use App\Models\BillingSetting;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BillingSetting> */
class BillingSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'invoice_prefix' => 'INV',
            'payment_terms_days' => 30,
        ];
    }
}
