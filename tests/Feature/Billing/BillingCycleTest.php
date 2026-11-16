<?php

use App\Enums\BillingCycle;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;

function cycleDay(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date)->startOfDay();
}

test('biweekly periods count forward from the start date', function () {
    $startsOn = cycleDay('2026-11-14');

    [$start, $end] = BillingCycle::Biweekly->periodContaining(cycleDay('2026-11-20'), $startsOn);
    expect($start->toDateString())->toBe('2026-11-14')
        ->and($end->toDateString())->toBe('2026-11-27');

    [$start, $end] = BillingCycle::Biweekly->periodContaining(cycleDay('2026-11-28'), $startsOn);
    expect($start->toDateString())->toBe('2026-11-28')
        ->and($end->toDateString())->toBe('2026-12-11');

    expect(BillingCycle::Biweekly->periodContaining(cycleDay('2026-11-13'), $startsOn))->toBeNull();
});

test('weekly periods are seven days', function () {
    [$start, $end] = BillingCycle::Weekly->periodContaining(cycleDay('2026-11-21'), cycleDay('2026-11-14'));

    expect($start->toDateString())->toBe('2026-11-21')
        ->and($end->toDateString())->toBe('2026-11-27');
});

test('monthly periods are calendar months', function () {
    [$start, $end] = BillingCycle::Monthly->periodContaining(cycleDay('2026-02-10'), null);

    expect($start->toDateString())->toBe('2026-02-01')
        ->and($end->toDateString())->toBe('2026-02-28');
});

test('cycles have labels', function () {
    expect(BillingCycle::Weekly->label())->toBe('Weekly')
        ->and(BillingCycle::Biweekly->label())->toBe('Every two weeks')
        ->and(BillingCycle::Monthly->label())->toBe('Calendar month');
});

test("the billing timezone is the first owner's", function () {
    $organization = Organization::factory()->create();
    $first = User::factory()->create(['timezone' => 'America/Chicago']);
    $second = User::factory()->create(['timezone' => 'Europe/Paris']);
    $organization->addMember($first, 'Owner');
    $this->travel(1)->minute();
    $organization->addMember($second, 'Owner');

    expect($organization->billingTimezone())->toBe('America/Chicago');
});

test('the billing timezone falls back to the app timezone', function () {
    $organization = Organization::factory()->create();
    $organization->addMember(User::factory()->create(['timezone' => null]), 'Owner');

    expect($organization->billingTimezone())->toBe(config('app.timezone'));
});
