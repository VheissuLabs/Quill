<?php

namespace Database\Seeders;

use App\Enums\IssueStatus;
use App\Models\BoardColumn;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Boards start with Todo, In progress and Done. The first board in each organization adds an extra
 * in-progress column before Done, so custom boards and the status mapping are exercised.
 */
class BoardColumnSeeder extends Seeder
{
    public function run(): void
    {
        Organization::all()->each(function (Organization $organization) {
            $owner = $organization->teams()->orderBy('id')->first() ?? $organization;

            $this->insertBeforeDone($owner, fake()->randomElement(['In review', 'QA', 'Blocked', 'Ready to ship']));
        });
    }

    protected function insertBeforeDone(Organization|Team $owner, string $name): void
    {
        $done = $owner->columns()->where('status', IssueStatus::Done)->orderBy('position')->first();

        BoardColumn::factory()->for($owner, 'owner')->create([
            'name' => $name,
            'position' => $done ? $done->position : $owner->columns()->count(),
        ]);

        $owner->columns()->orderBy('position')->orderByDesc('id')->get()
            ->each(fn (BoardColumn $column, int $position) => $column->update(['position' => $position]));
    }
}
