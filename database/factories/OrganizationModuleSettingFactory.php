<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationModuleSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationModuleSetting> */
class OrganizationModuleSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'module' => 'feedback',
            'capability' => '',
            'enabled' => true,
        ];
    }
}
