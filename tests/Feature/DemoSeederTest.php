<?php

use App\Models\Organization;
use App\Models\Project;
use Database\Seeders\DemoSeeder;

test('the demo seeder builds the demo workspace and can be rerun without duplicating it', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $organization = Organization::where('slug', 'vheissulabs')->firstOrFail();
    $board = Project::where('slug', 'gitdifferent')->firstOrFail();

    expect(Organization::count())->toBe(2);
    expect($organization->teams()->count())->toBe(2);
    expect($organization->projects()->count())->toBe(2);
    expect(Organization::where('slug', 'notarydash')->firstOrFail()->projects()->count())->toBe(2);
    expect($board->features()->count())->toBe(7);
    expect($board->features()->where('title', 'Stacked pull requests')->firstOrFail()->votes()->count())->toBe(5);
    expect($board->issues()->count())->toBe(2);
    expect($organization->sprints()->count())->toBe(1);
});
