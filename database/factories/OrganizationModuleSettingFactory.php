<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationModuleSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationModuleSetting>
 */
class OrganizationModuleSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'module' => 'feature_voting',
            'capability' => '',
            'enabled' => true,
        ];
    }
}
