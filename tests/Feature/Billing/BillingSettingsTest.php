<?php

use App\Enums\PaymentMethod;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

function squarePayload(array $overrides = []): array
{
    return array_merge([
        'invoice_prefix' => 'INV',
        'payment_terms_days' => 30,
        'square_environment' => 'production',
        'square_access_token' => 'EAAA-secret',
        'square_location_id' => 'L1',
        'square_webhook_signature_key' => 'sig-secret',
        'payment_instructions' => 'Zelle: pay@x.test',
    ], $overrides);
}

test('square settings save and secrets stay hidden', function () {
    [$organization, $admin] = billingManager();

    $this->actingAs($admin)
        ->patch(route('organizations.billing.update', $organization), squarePayload())
        ->assertSessionHasNoErrors();

    $settings = $organization->billingSettings()->fresh();
    expect($settings)
        ->square_environment->toBe('production')
        ->square_access_token->toBe('EAAA-secret')
        ->square_location_id->toBe('L1')
        ->square_webhook_signature_key->toBe('sig-secret')
        ->payment_instructions->toBe('Zelle: pay@x.test')
        ->and(DB::table('billing_settings')->value('square_access_token'))->not->toBe('EAAA-secret')
        ->and($settings->squareConfigured())->toBeTrue();

    $response = $this->actingAs($admin)
        ->get(route('organizations.billing.edit', $organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('square.environment', 'production')
            ->where('square.hasAccessToken', true)
            ->where('square.hasSignatureKey', true)
            ->where('square.locationId', 'L1')
            ->where('paymentInstructions', 'Zelle: pay@x.test'));

    expect($response->getContent())->not->toContain('EAAA-secret')->not->toContain('sig-secret');
});

test('blank secret inputs keep the saved ones', function () {
    [$organization, $admin] = billingManager();
    $url = route('organizations.billing.update', $organization);

    $this->actingAs($admin)->patch($url, squarePayload());
    $this->actingAs($admin)->patch($url, squarePayload([
        'square_access_token' => '',
        'square_webhook_signature_key' => null,
        'square_location_id' => 'L2',
    ]))->assertSessionHasNoErrors();

    expect($organization->billingSettings()->fresh())
        ->square_access_token->toBe('EAAA-secret')
        ->square_webhook_signature_key->toBe('sig-secret')
        ->square_location_id->toBe('L2');

    $this->actingAs($admin)->patch($url, squarePayload(['square_access_token' => 'EAAA-new']));

    expect($organization->billingSettings()->fresh()->square_access_token)->toBe('EAAA-new');
});

test('the webhook url is shown and stable', function () {
    [$organization, $admin] = billingManager();
    $url = route('organizations.billing.edit', $organization);

    $first = $this->actingAs($admin)->get($url)->viewData('page')['props']['square']['webhookUrl'];
    $token = $organization->billingSettings()->fresh()->square_webhook_token;

    expect($token)->toHaveLength(40)
        ->and($first)->toBe(rtrim(config('app.url'), '/').'/webhooks/square/'.$token)
        ->and($this->actingAs($admin)->get($url)->viewData('page')['props']['square']['webhookUrl'])->toBe($first);
});

test('square environment must be sandbox or production', function () {
    [$organization, $admin] = billingManager();

    $this->actingAs($admin)
        ->patch(route('organizations.billing.update', $organization), squarePayload(['square_environment' => 'staging']))
        ->assertSessionHasErrors('square_environment');
});

test('members without invoice management cannot change square settings', function () {
    [$organization, $member] = billingManager('Member');

    $this->actingAs($member)
        ->patch(route('organizations.billing.update', $organization), squarePayload())
        ->assertForbidden();

    expect($organization->billingSettings()->fresh()->square_access_token)->toBeNull();
});

test('payment methods include Zelle and Cash App', function () {
    expect(PaymentMethod::Zelle->label())->toBe('Zelle')
        ->and(PaymentMethod::Zelle->value)->toBe('zelle')
        ->and(PaymentMethod::CashApp->label())->toBe('Cash App')
        ->and(PaymentMethod::CashApp->value)->toBe('cash_app');
});

test('square secrets are hidden when the settings are serialized', function () {
    $settings = Organization::factory()->create()->billingSettings();
    $settings->update(['square_access_token' => 'token', 'square_webhook_signature_key' => 'key']);

    expect($settings->fresh()->toArray())->not->toHaveKeys(['square_access_token', 'square_webhook_signature_key']);
});
