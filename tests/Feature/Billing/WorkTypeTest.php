<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\WorkType;

function workTypeManager(): array
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');

    return [$organization, $admin];
}

test('a manager adds a work type', function () {
    [$organization, $admin] = workTypeManager();

    $this->actingAs($admin)
        ->post(route('organizations.work-types.store', $organization), ['name' => ' Design ', 'color' => 'blue'])
        ->assertRedirect();

    expect($organization->workTypes()->sole())
        ->name->toBe('Design')
        ->color->toBe('blue')
        ->is_active->toBeTrue();
});

test('work type names are unique within a workspace', function () {
    [$organization, $admin] = workTypeManager();
    WorkType::factory()->for($organization)->create(['name' => 'Design']);

    $this->actingAs($admin)
        ->post(route('organizations.work-types.store', $organization), ['name' => 'Design'])
        ->assertSessionHasErrors('name');

    $other = Organization::factory()->create();
    $other->addMember($admin, 'Admin');

    $this->actingAs($admin)
        ->post(route('organizations.work-types.store', $other), ['name' => 'Design'])
        ->assertSessionHasNoErrors();
});

test('a manager renames and deactivates a work type', function () {
    [$organization, $admin] = workTypeManager();
    $workType = WorkType::factory()->for($organization)->create(['name' => 'Design']);

    $this->actingAs($admin)
        ->patch(route('organizations.work-types.update', [$organization, $workType]), [
            'name' => 'Design',
            'color' => 'red',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($workType->fresh())->is_active->toBeFalse()->color->toBe('red');
});

test('a manager deletes a work type', function () {
    [$organization, $admin] = workTypeManager();
    $workType = WorkType::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->delete(route('organizations.work-types.destroy', [$organization, $workType]))
        ->assertRedirect();

    expect($organization->workTypes()->count())->toBe(0);
});

test('work types of another workspace cannot be touched', function () {
    [$organization, $admin] = workTypeManager();
    $foreign = WorkType::factory()->for(Organization::factory())->create();

    $this->actingAs($admin)
        ->delete(route('organizations.work-types.destroy', [$organization, $foreign]))
        ->assertNotFound();
});

test('members without invoice management cannot add work types', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->post(route('organizations.work-types.store', $organization), ['name' => 'Design'])
        ->assertForbidden();
});
