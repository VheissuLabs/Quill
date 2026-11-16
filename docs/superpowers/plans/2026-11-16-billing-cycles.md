# Billing Cycles Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Each client can be billed automatically on its own pay period: Quill keeps one cycle Draft open per client, reminds and sends it at period end, and marks unpaid invoices Overdue.

**Architecture:** New client columns hold the schedule; `BillingCycle::periodContaining()` computes periods. Single-purpose actions (`OpenBillingPeriod`, `RestartBillingCycle`, `RemindClosingInvoices`, `CloseBillingPeriods`, `MarkOverdueInvoices`) are driven by a `billing:run` command scheduled every minute. Every step is idempotent and decides from dates and existing rows.

**Tech Stack:** Laravel 13, Pest, Inertia 3 + Vue 3 + shadcn-vue, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-11-16-billing-cycles-design.md` (builds on `2026-11-15-billing-invoices-design.md`)

## Global Constraints

- Work on `main`; commit per task; do not push.
- `BillingCycle` cases: `weekly`, `biweekly`, `monthly`. Labels: "Weekly", "Every two weeks", "Calendar month".
- Weekly = 7-day and biweekly = 14-day blocks counted forward from `billing_cycle_starts_on`; monthly = calendar month; a day before `starts_on` has no period.
- `closes_at` = 23:59:00 on the period's last day in the billing timezone, stored UTC. Reminder from 15:00 billing-timezone on that day.
- Billing timezone = the `timezone` of the workspace's first owner (earliest member holding the Owner role), else `config('app.timezone')`.
- A client has at most one unsent cycle invoice (`closes_at` not null, `sent_at` null, status not Void) at a time.
- Everything is gated by `invoice:manage` and runs only where `OrganizationCapability::BillingCycles` is usable (module Billing, on by default).
- Arch rules (tests/Architecture): models carry `#[UseFactory]`/`#[ObservedBy]`; controllers under 100 lines, no private/protected methods, no Request/Gate/abort; actions in `App\Actions\Billing` with `handle()`, no `auth()`/`abort`; notifications are `ShouldQueue` with `via()`; every Http\Requests class has `rules()`. No new top-level app directories.
- Vue: single quotes, 4-space indent, never split a closing tag across lines; run `npm run check:fix` before committing Vue.
- PHP: `vendor/bin/pint --dirty --format agent`; PHPStan (`composer` phpstan script) stays at 0 errors; run `composer ide-helper` after adding columns.

## Review Focus

1. A job run that is late, doubled or overlapping must not create a second cycle Draft or send twice — pinned in Task 3 and Task 5.
2. Turning the cycle on today must not back-date a period; days before `starts_on` have none — pinned in Task 1 and Task 3.
3. An early send at 23:30 on the last day vs. the job's close at 23:59 — only one path restarts the cycle — pinned in Task 4.
4. A biweekly client switched to monthly mid-period keeps its open Draft and continues with no gap or overlap — pinned in Task 3.
5. Clients billed through another can't get a cycle, and a cycle client can't be set billed-through — pinned in Task 2.

---

### Task 1: Capability, columns, period math, billing timezone

**Files:**

- Create: `app/Enums/BillingCycle.php`, migration `add_billing_cycle_to_clients_table`, migration `add_cycle_columns_to_invoices_table`
- Modify: `app/Enums/OrganizationCapability.php`, `app/Http/Requests/Organizations/UpdateOrganizationModulesRequest.php`, `resources/js/pages/organization/Settings.vue`, `resources/js/types/organizations.ts`, `database/factories/OrganizationFactory.php` (wire `BillingCycles` exactly as commit 4cc1902 wired `Invoices`), `app/Models/Client.php` (casts), `app/Models/Invoice.php` (casts), `app/Models/Organization.php`
- Test: `tests/Unit/BillingCycleTest.php` (or `tests/Feature/Billing/BillingCycleTest.php` if Unit tests here can't boot dates), `tests/Feature/Billing/BillingModuleTest.php`

**Interfaces:**

- Produces: `BillingCycle::periodContaining(CarbonImmutable $day, ?CarbonImmutable $startsOn): ?array{0: CarbonImmutable, 1: CarbonImmutable}` (dates, start-of-day); `BillingCycle::label(): string`; `OrganizationCapability::BillingCycles` (`billing_cycles`, request key `billing_billing_cycles_enabled` following the existing pattern); `Organization::billingTimezone(): string`.
- Columns: clients `billing_cycle` (string nullable), `billing_cycle_starts_on` (date nullable), `billing_cc_emails` (json nullable), `payment_terms_days` (unsigned smallint nullable), `billing_opened_through` (date nullable); invoices `closes_at`, `reminded_at` (timestamps nullable). Casts: enum, `date`, `array`, `integer`, `datetime`.

- [ ] **Step 1: Failing tests**
    - `biweekly periods count forward from the start date`: startsOn Sat 2026-11-14; day 2026-11-20 → [11-14, 11-27]; day 2026-11-28 → [11-28, 12-11]; day 2026-11-13 → null.
    - `weekly periods are seven days`: startsOn 2026-11-14, day 2026-11-21 → [11-21, 11-27].
    - `monthly periods are calendar months`: day 2026-02-10, startsOn null → [02-01, 02-28].
    - `the billing timezone is the first owner's`: org with owner A (`timezone` 'America/Chicago', joined first) and later owner B ('Europe/Paris') → 'America/Chicago'; owner with null timezone → `config('app.timezone')`.
    - In BillingModuleTest: `billing_billing_cycles_enabled` false → `canUse(BillingCycles)` false; Billing module off → false.
- [ ] **Step 2: Run, expect failures** — `php artisan test --compact --filter=BillingCycle` and BillingModuleTest.
- [ ] **Step 3: Implement** enum, migrations, casts, capability wiring, `billingTimezone()`. Run `composer ide-helper`.
- [ ] **Step 4: Run tests + `php artisan test --compact tests/Feature/Billing tests/Feature/Organizations` → PASS; phpstan 0.**
- [ ] **Step 5: Commit** "Clients can carry a billing cycle".

### Task 2: Client billing cycle settings

**Files:**

- Modify: `app/Http/Requests/Clients/ClientRequest.php`, `app/Http/Controllers/Clients/ClientController.php` (`edit` billing payload, `update`), `resources/js/pages/clients/Edit.vue` (billing section), `resources/js/types/clients.ts` (or wherever the billing prop type lives)
- Create: `app/Actions/Billing/StopBillingCycle.php`
- Test: `tests/Feature/Billing/ClientBillingTest.php`

**Interfaces:**

- Consumes: Task 1 columns, `BillingCycle`.
- Produces: `StopBillingCycle::handle(Client $client): void` — clears `closes_at` on the client's unsent cycle invoice. Edit payload adds `billing.cycle`, `billing.cycleStartsOn`, `billing.ccEmails`, `billing.paymentTermsDays`, `billing.currentPeriod` (`{start, end}` strings or null, from `periodContaining(today in billing timezone)`), `billing.cycles` (`[{value,label}]`).

- [ ] **Step 1: Failing tests**
    - `a manager sets a biweekly cycle`: PATCH with `billing_cycle=biweekly`, `billing_cycle_starts_on=2026-11-14`, `billing_cc_emails=['a@x.test']`, `payment_terms_days=7` → saved.
    - `turning a cycle on defaults the start to today`: `billing_cycle=weekly`, no start → `billing_cycle_starts_on` = today (billing timezone), with `travelTo('2026-11-16 10:00')`.
    - `monthly needs no start date` and stores null start.
    - `a client billed through another cannot have a cycle`: 422 on `billing_cycle`.
    - `a cycle client cannot be set billed through`: client with cycle, PATCH `billed_through_client_id` → 422 on `billed_through_client_id`.
    - `turning the cycle off makes the open cycle draft manual`: client with cycle and an invoice `closes_at` set, unsent → PATCH `billing_cycle=null` → invoice `closes_at` null, still Draft.
    - `changing the cycle leaves the open draft alone`: biweekly → monthly → open invoice `closes_at` unchanged.
    - `cycle fields are refused without invoice:manage` (follow the existing rate test pattern) and `cc emails must be emails`.
- [ ] **Step 2: Run, expect failures.**
- [ ] **Step 3: Implement** rules (cycle `nullable`, `Rule::enum`; start `nullable|date`, defaulted in `prepareForValidation` when a cycle is given without one and the cycle isn't monthly; cc `array`, `*.email:rfc`; terms `nullable|integer|min:0|max:365`; the two billed-through refusals), controller calls `StopBillingCycle` when the cycle goes from set to null. Edit.vue: Billing cycle select (None + `billing.cycles`), "Period starts on" date (weekly/biweekly only), CC (comma separated, as on the invoice page), Payment terms (days); hide cycle fields when billed-through is set; show "Current period: Nov 14 – Nov 27" when `currentPeriod`.
- [ ] **Step 4: Run** ClientBillingTest + `tests/Feature/Clients` → PASS; `npm run check`, `npm run types:check`, `npm run build`.
- [ ] **Step 5: Commit** "Clients choose their own pay period".

### Task 3: Opening periods

**Files:**

- Create: `app/Actions/Billing/OpenBillingPeriod.php`
- Modify: `app/Models/Client.php` (`openCycleInvoice()` relation/query), `database/factories/InvoiceFactory.php` (state `cycle(CarbonImmutable $start, CarbonImmutable $end)`)
- Test: `tests/Feature/Billing/OpenBillingPeriodTest.php`

**Interfaces:**

- Consumes: `BillingCycle::periodContaining`, `Organization::billingTimezone`, layer 1 `CreateInvoice::handle(Organization, Client, array)`.
- Produces: `OpenBillingPeriod::handle(Client $client, CarbonImmutable $today): ?Invoice`; `Client::openCycleInvoice(): ?Invoice` (unsent, non-Void, `closes_at` not null).

- [ ] **Step 1: Failing tests** (all with `travelTo` and an owner in 'America/Chicago')
    - `it opens the current period`: biweekly from 2026-11-14, today 11-16 → invoice with service period 11-14..11-27, issue date 11-27, due 12-04 (terms 7), contact = billing contact, cc = client cc, `closes_at` = 2026-11-28 05:59:00 UTC, `billing_opened_through` = 11-27.
    - `terms fall back to workspace settings` (client terms null, settings 30 → due 12-27).
    - `it returns the open draft instead of opening another`: call twice → one invoice.
    - `nothing before the start date`: start 11-20, today 11-16 → null, no invoice.
    - `no cycle, no invoice`.
    - `after downtime the next draft covers the missed days`: opened_through 11-27, today 12-20 (period 12-12..12-25) → service start 11-28, end 12-25.
    - `a cycle change continues without gap or overlap`: opened_through 11-27 (biweekly), switched to monthly, today 11-28 → service 11-28..11-30.
    - `it links the sprint with exactly the period's dates`: workspace sprint 11-14..11-27 → `sprint_id` set; team sprint with same dates also exists → the workspace one; sprint 11-14..11-28 → null.
    - `it opens without a billing contact` (contact_id null).
- [ ] **Step 2: Run, expect failures.**
- [ ] **Step 3: Implement** in one transaction with `Client::whereKey()->lockForUpdate()`; re-check `openCycleInvoice()` inside the lock.
- [ ] **Step 4: Run → PASS; phpstan 0.**
- [ ] **Step 5: Commit** "Clients on a cycle always have an open invoice".

### Task 4: Sending early and deleting restart the cycle

**Files:**

- Create: `app/Actions/Billing/RestartBillingCycle.php`
- Modify: `app/Actions/Billing/SendInvoice.php`, `app/Http/Controllers/Billing/InvoiceController.php` (`destroy`), `app/Actions/Billing/DuplicateInvoice.php` only if needed so it never copies `closes_at`/`reminded_at`
- Test: `tests/Feature/Billing/RestartBillingCycleTest.php`, `tests/Feature/Billing/DuplicateInvoiceTest.php`

**Interfaces:**

- Consumes: `OpenBillingPeriod`, `Client::openCycleInvoice`.
- Produces: `RestartBillingCycle::handle(Invoice $invoice, CarbonImmutable $on): ?Invoice` — sets the invoice's `service_period_end` = `$on` and `closes_at` = null (unless deleting), sets client `billing_opened_through` = `$on`, for weekly/biweekly `billing_cycle_starts_on` = `$on + 1`, then opens the next Draft immediately: weekly/biweekly via `OpenBillingPeriod` for `$on + 1`; monthly from `$on + 1` to the end of the following calendar month. Returns the new Draft.
- "Early" = a cycle invoice (`closes_at` not null) sent or deleted while `now < closes_at`. `SendInvoice` calls `RestartBillingCycle` inside its transaction only when early; the job's close (now ≥ `closes_at`) never restarts.

- [ ] **Step 1: Failing tests**
    - `sending a biweekly draft early restarts the schedule`: period 11-14..11-27, send on Wed 11-25 → sent invoice service end 11-25, `closes_at` null; client starts_on 11-26, opened_through 11-25; a new Draft 11-26..12-09 exists.
    - `sending a monthly draft early runs the next draft to the end of next month`: Nov draft sent 11-25 → new Draft 11-26..12-31; starts_on unchanged.
    - `deleting the open cycle draft restarts the same way`: DELETE on 11-25 → new Draft 11-26..12-09.
    - `time logged after an early send lands on the new draft`: after early send, `$client->openCycleInvoice()` is the new Draft.
    - `the job's close at period end does not restart`: send with `travelTo` after `closes_at` → starts_on unchanged, opened_through unchanged.
    - `sending a manual invoice never restarts anything` (closes_at null).
    - DuplicateInvoiceTest: `duplicates are never cycle invoices` → copy `closes_at` and `reminded_at` null.
- [ ] **Step 2: Run, expect failures.**
- [ ] **Step 3: Implement.** In `destroy`, call the action before deleting when the invoice is an early cycle invoice (the controller passes today in the billing timezone; no branching logic beyond one call — put the "is early" check in the action).
- [ ] **Step 4: Run** these + `tests/Feature/Billing` → PASS.
- [ ] **Step 5: Commit** "Sending a cycle invoice early restarts the client's schedule".

### Task 5: The job — reminders, closing, overdue

**Files:**

- Create: `app/Actions/Billing/RemindClosingInvoices.php`, `app/Actions/Billing/CloseBillingPeriods.php`, `app/Actions/Billing/MarkOverdueInvoices.php`, `app/Console/Commands/RunBilling.php` (`billing:run`), `app/Notifications/InvoiceClosingSoon.php`, `app/Notifications/InvoiceCouldNotBeSent.php`
- Modify: `routes/console.php` (schedule `billing:run` `everyMinute()->withoutOverlapping()`)
- Test: `tests/Feature/Billing/BillingRunTest.php`

**Interfaces:**

- Consumes: `OpenBillingPeriod`, `SendInvoice`, `Organization::billingTimezone`, `canUse(BillingCycles)`, `allowsInvoiceManagementBy`.
- Produces: each action `handle(CarbonImmutable $now): void` (UTC now; each converts per workspace). `billing:run` calls, in order: open (every cycle client in eligible workspaces, today in its billing timezone), remind, close, overdue.
- Notifications: mail + database, queued, to members who `allowsInvoiceManagementBy`; both link to `invoices.show`. Copy: "INV-… for {client} sends tonight at 11:59 PM" / "INV-… for {client} couldn't be sent: it has no contact."

- [ ] **Step 1: Failing tests** (`Notification::fake()`, `Mail::fake()` as layer 1's send tests do; owner in 'America/Chicago'; period ends 11-27, `closes_at` 2026-11-28 05:59 UTC)
    - `it reminds at 3 PM on the last day when there are lines`: run at 11-27 20:59 UTC → none; 21:00 UTC → `InvoiceClosingSoon` to the manager, `reminded_at` set; run again → no second notification.
    - `no lines, no reminder`.
    - `it sends at 11:59 PM`: run at 05:58 UTC 11-28 → unsent; 05:59 → `sent_at` set, `InvoiceSent` once; run again → still once; the next period's Draft exists (open step).
    - `an empty period is deleted without email`: no lines, no payments → invoice gone, no notification.
    - `a draft with a payment but no lines is sent, not deleted`.
    - `no contact: it becomes a manual draft and managers are told`: `closes_at` null, `InvoiceCouldNotBeSent` once; the next period still opens.
    - `a draft already paid in full by a deposit is still sent`.
    - `sent invoices past due become overdue; partial stays partial`: Sent due 11-15, run 11-16 07:00 UTC (11-16 local) → Overdue; Partial due 11-15 → Partial; due 11-16 → unchanged.
    - `workspaces without the cycles capability are skipped`.
    - `the command is scheduled every minute`: assert via `Schedule` events list contains `billing:run` with expression `* * * * *`.
- [ ] **Step 2: Run, expect failures.**
- [ ] **Step 3: Implement.** Queries filter `closes_at` not null, `sent_at` null, status ≠ Void; close deletes only when no lines and no payments; overdue updates Sent invoices with `due_date` < local today to Overdue.
- [ ] **Step 4: Run** BillingRunTest + `tests/Feature/Billing` + `tests/Architecture` → PASS; phpstan 0.
- [ ] **Step 5: Commit** "Cycle invoices remind, send and go overdue on their own".

### Task 6: Showing cycles in the UI

**Files:**

- Modify: `app/Http/Resources/InvoiceResource.php` (`closesAt` ISO string or null), `app/Http/Resources/InvoiceListResource.php` (`isCurrentPeriod` bool = cycle invoice unsent), `app/Http/Controllers/Clients/ClientController.php` (`show`: order the open cycle invoice first), `resources/js/types/invoices.ts`, `resources/js/pages/invoices/Show.vue`, `resources/js/pages/clients/Show.vue`
- Test: `tests/Feature/Billing/InvoiceTest.php`, `tests/Feature/Clients/ClientOverviewTest.php`

**Interfaces:**

- Consumes: Task 1 columns, `Client::openCycleInvoice`.

- [ ] **Step 1: Failing tests**
    - InvoiceTest `a cycle draft carries when it sends`: `invoice.closesAt` = the ISO timestamp; manual invoice → null.
    - ClientOverviewTest `the current period's invoice is listed first and marked`: older sent invoice + open cycle Draft → `invoices.0.id` = the Draft, `invoices.0.isCurrentPeriod` true, `invoices.1.isCurrentPeriod` false.
- [ ] **Step 2: Run, expect failures.**
- [ ] **Step 3: Implement.** Show.vue: under the header, when `closesAt` and not sent, a muted line "Sends automatically {Nov 27} at {11:59 PM}" (format in the viewer's locale). clients/Show.vue: a "Current period" badge on that row.
- [ ] **Step 4: Run** tests; `npm run check`, `npm run types:check`, `npm run build`.
- [ ] **Step 5: Commit** "Cycle invoices say when they send".

### Task 7: Verification

- [ ] Full suite `php artisan test --compact`; pint; phpstan; `npm run check`; `npm run types:check`; `npm run build`.
- [ ] Migrate local DB. In a generated (not VheissuLabs/NotaryDash) workspace: set a client biweekly from today, run `php artisan billing:run`, confirm the Draft opens with the right dates and "Sends automatically …" shows; send it early and confirm the next Draft opens from tomorrow.
