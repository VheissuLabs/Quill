<?php

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;

test('billing is on for a workspace with no setting', function () {
    expect(Organization::factory()->create()->canUse(OrganizationCapability::Invoices))->toBeTrue();
});

test('an owner can switch billing off', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');

    $this
        ->actingAs($owner)
        ->patch(route('organizations.modules.update', $organization), [
            'feedback_enabled' => true,
            'feedback_boards_enabled' => true,
            'feedback_voting_enabled' => true,
            'development_enabled' => true,
            'development_issues_enabled' => true,
            'development_sprints_enabled' => true,
            'development_workflow_enabled' => true,
            'teams_enabled' => true,
            'billing_enabled' => false,
            'billing_invoices_enabled' => true,
            'billing_billing_cycles_enabled' => true,
        ])
        ->assertRedirect(route('organizations.edit', $organization));

    expect($organization->fresh()->canUse(OrganizationCapability::Invoices))->toBeFalse();
});

test('owners and admins can manage invoices, members cannot', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($admin, 'Admin');
    $organization->addMember($member, 'Member');

    expect($owner->hasOrganizationPermission($organization, OrganizationPermission::ManageInvoices))->toBeTrue()
        ->and($admin->hasOrganizationPermission($organization, OrganizationPermission::ManageInvoices))->toBeTrue()
        ->and($member->hasOrganizationPermission($organization, OrganizationPermission::ManageInvoices))->toBeFalse();
});

test('billing cycles can be switched off on their own', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');

    $this
        ->actingAs($owner)
        ->patch(route('organizations.modules.update', $organization), [
            'feedback_enabled' => true,
            'feedback_boards_enabled' => true,
            'feedback_voting_enabled' => true,
            'development_enabled' => true,
            'development_issues_enabled' => true,
            'development_sprints_enabled' => true,
            'development_workflow_enabled' => true,
            'teams_enabled' => true,
            'billing_enabled' => true,
            'billing_invoices_enabled' => true,
            'billing_billing_cycles_enabled' => false,
        ])
        ->assertRedirect(route('organizations.edit', $organization));

    $organization = $organization->fresh();

    expect($organization->canUse(OrganizationCapability::BillingCycles))->toBeFalse()
        ->and($organization->canUse(OrganizationCapability::Invoices))->toBeTrue();
});

test('billing cycles need the billing module', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');

    $this
        ->actingAs($owner)
        ->patch(route('organizations.modules.update', $organization), [
            'feedback_enabled' => true,
            'feedback_boards_enabled' => true,
            'feedback_voting_enabled' => true,
            'development_enabled' => true,
            'development_issues_enabled' => true,
            'development_sprints_enabled' => true,
            'development_workflow_enabled' => true,
            'teams_enabled' => true,
            'billing_enabled' => false,
            'billing_invoices_enabled' => true,
            'billing_billing_cycles_enabled' => true,
        ]);

    expect($organization->fresh()->canUse(OrganizationCapability::BillingCycles))->toBeFalse();
});
