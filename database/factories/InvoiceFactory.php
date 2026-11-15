<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => fn (array $attributes) => Client::factory()->state(['organization_id' => $attributes['organization_id']]),
            'number' => 'INV-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Draft,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function void(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Void,
            'sent_at' => now(),
            'voided_at' => now(),
        ]);
    }
}
