<?php

use App\Events\OrganizationChanged;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can reorder the product modules for everyone in the organization', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');

    Event::fake([OrganizationChanged::class]);

    $this->actingAs($owner)
        ->patch(route('organizations.modules.order.update', $organization), ['order' => ['billing', 'support', 'relationships', 'feedback', 'development']])
        ->assertRedirect();

    expect($organization->fresh()->moduleOrder())->toBe(['billing', 'support', 'relationships', 'feedback', 'development']);
    Event::assertDispatched(OrganizationChanged::class);

    $this->actingAs($owner)
        ->get(route('dashboard', $organization))
        ->assertInertia(fn (Assert $page) => $page->where('currentOrganization.moduleOrder', ['billing', 'support', 'relationships', 'feedback', 'development']));
});

test('members cannot reorder the product modules', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->patch(route('organizations.modules.order.update', $organization), ['order' => ['billing', 'support', 'relationships', 'feedback', 'development']])
        ->assertForbidden();
});

test('an order must list every section exactly once', function (array $order) {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');

    $this->actingAs($owner)
        ->patch(route('organizations.modules.order.update', $organization), ['order' => $order])
        ->assertSessionHasErrors();
})->with([
    'missing a section' => [['billing', 'support', 'relationships', 'feedback']],
    'a duplicate' => [['billing', 'billing', 'feedback', 'development']],
    'an unknown section' => [['billing', 'support', 'relationships', 'feedback', 'teams']],
]);

test('organizations without a saved order use the default order', function () {
    expect(Organization::factory()->create()->moduleOrder())->toBe(['feedback', 'development', 'support', 'relationships', 'billing']);
});
