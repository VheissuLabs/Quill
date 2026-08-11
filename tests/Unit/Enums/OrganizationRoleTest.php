<?php

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;

test('owners hold every permission', function () {
    expect(OrganizationRole::Owner->permissions())
        ->toEqual(OrganizationPermission::cases());
});

test('admins can update the organization but not delete it', function () {
    expect(OrganizationRole::Admin->hasPermission(OrganizationPermission::UpdateOrganization))->toBeTrue();
    expect(OrganizationRole::Admin->hasPermission(OrganizationPermission::DeleteOrganization))->toBeFalse();
});

test('members and clients hold no permissions', function () {
    expect(OrganizationRole::Member->permissions())->toBe([]);
    expect(OrganizationRole::Client->permissions())->toBe([]);
});

test('clients rank below members', function () {
    expect(OrganizationRole::Client->isAtLeast(OrganizationRole::Member))->toBeFalse();
    expect(OrganizationRole::Member->isAtLeast(OrganizationRole::Client))->toBeTrue();
    expect(OrganizationRole::Owner->isAtLeast(OrganizationRole::Owner))->toBeTrue();
});

test('every role except client is a billable seat', function () {
    expect(OrganizationRole::Owner->isBillable())->toBeTrue();
    expect(OrganizationRole::Admin->isBillable())->toBeTrue();
    expect(OrganizationRole::Member->isBillable())->toBeTrue();
    expect(OrganizationRole::Client->isBillable())->toBeFalse();
});

test('assignable roles exclude owner and client', function () {
    expect(OrganizationRole::assignable())->toBe([
        ['value' => 'admin', 'label' => 'Admin'],
        ['value' => 'member', 'label' => 'Member'],
    ]);
});
