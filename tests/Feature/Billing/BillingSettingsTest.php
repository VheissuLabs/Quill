<?php

use App\Models\Organization;
use App\Models\User;

function billingManager(string $role = 'Admin'): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);

    return [$organization, $user];
}

test('billing settings are created with defaults on first access', function () {
    $organization = Organization::factory()->create();

    expect($organization->billingSettings())
        ->invoice_prefix->toBe('INV')
        ->payment_terms_days->toBe(30)
        ->and($organization->billingSettings()->is($organization->billingSettings()))->toBeTrue();

    $this->assertDatabaseCount('billing_settings', 1);
});

test('a manager sees the billing settings page', function () {
    [$organization, $admin] = billingManager();

    $this->actingAs($admin)
        ->get(route('organizations.billing.edit', $organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('billing/Settings')
            ->where('settings.invoice_prefix', 'INV')
            ->has('workTypes', 0));
});

test('a manager updates billing settings', function () {
    [$organization, $admin] = billingManager();

    $this->actingAs($admin)
        ->patch(route('organizations.billing.update', $organization), [
            'invoice_prefix' => 'QL',
            'default_rate' => '125.00',
            'payment_terms_days' => 14,
            'tax_rate' => '8.25',
            'default_notes' => 'Thank you.',
            'default_terms' => 'Net 14.',
            'from_address' => "Quill LLC\n1 Main St",
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($organization->billingSettings()->fresh())
        ->invoice_prefix->toBe('QL')
        ->default_rate->toBe('125.00')
        ->tax_rate->toBe('8.25')
        ->payment_terms_days->toBe(14)
        ->from_address->toBe("Quill LLC\n1 Main St");
});

test('billing settings reject an invalid tax rate', function () {
    [$organization, $admin] = billingManager();

    $this->actingAs($admin)
        ->patch(route('organizations.billing.update', $organization), [
            'invoice_prefix' => 'INV',
            'payment_terms_days' => 30,
            'tax_rate' => '150',
        ])
        ->assertSessionHasErrors('tax_rate');
});

test('members without invoice management cannot see billing settings', function () {
    [$organization, $member] = billingManager('Member');

    $this->actingAs($member)->get(route('organizations.billing.edit', $organization))->assertForbidden();
    $this->actingAs($member)->patch(route('organizations.billing.update', $organization), [
        'invoice_prefix' => 'X',
        'payment_terms_days' => 30,
    ])->assertForbidden();
});

test('billing settings are 403 when billing is switched off', function () {
    [$organization, $admin] = billingManager();
    $organization->updateModuleSettings(['billing_enabled' => false]);

    $this->actingAs($admin)->get(route('organizations.billing.edit', $organization))->assertForbidden();
});
