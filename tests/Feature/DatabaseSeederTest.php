<?php

use App\Models\BoardColumn;
use App\Models\Sprint;
use Database\Seeders\BoardColumnSeeder;
use Database\Seeders\OrganizationMembershipSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\SprintSeeder;
use Database\Seeders\TeamSeeder;
use Database\Seeders\UserSeeder;

/**
 * The board seeders reach for their owner through a factory relation name, which nothing checks
 * until they run. The slower content seeders are left out; they do not touch board ownership.
 */
test('the board and sprint seeders give everything they create an owner', function () {
    $this->seed([
        PermissionsSeeder::class,
        UserSeeder::class,
        OrganizationSeeder::class,
        OrganizationMembershipSeeder::class,
        TeamSeeder::class,
        BoardColumnSeeder::class,
        SprintSeeder::class,
    ]);

    expect(BoardColumn::count())->toBeGreaterThan(0);
    expect(Sprint::count())->toBeGreaterThan(0);
    expect(BoardColumn::whereNull('owner_id')->count())->toBe(0);
    expect(Sprint::whereNull('owner_id')->count())->toBe(0);
});
