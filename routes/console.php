<?php

use App\Actions\Billing\CloseBillingPeriods;
use App\Actions\Billing\MarkOverdueInvoices;
use App\Actions\Billing\OpenBillingPeriod;
use App\Actions\Billing\RemindClosingInvoices;
use App\Models\Client;
use App\Models\OrganizationInvitation;
use App\Support\BillingEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    OrganizationInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired organization invitations');

Artisan::command('billing:run', function (
    OpenBillingPeriod $open,
    RemindClosingInvoices $remind,
    CloseBillingPeriods $close,
    MarkOverdueInvoices $overdue,
    BillingEligibility $eligibility,
): int {
    $now = CarbonImmutable::now('UTC');

    Client::query()
        ->whereNotNull('billing_cycle')
        ->with('organization')
        ->get()
        ->groupBy('organization_id')
        ->each(function (Collection $clients) use ($open, $eligibility, $now): void {
            $organization = $clients->first()->organization;

            if (! $eligibility->isEligible($organization)) {
                return;
            }

            $timezone = $organization->billingTimezone();
            $today = CarbonImmutable::parse($now->setTimezone($timezone)->toDateString(), $timezone);

            $clients->each(fn (Client $client) => $open->handle($client, $today));
        });

    $remind->handle($now);
    $close->handle($now);
    $overdue->handle($now);

    return 0;
})->purpose('Open, remind, close and age billing cycle invoices');

Schedule::command('billing:run')->everyMinute()->withoutOverlapping()->description('Run billing cycles');
