<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamModuleSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeamModuleSetting> */
class TeamModuleSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'module' => 'development',
            'capability' => 'sprints',
            'enabled' => true,
        ];
    }
}
