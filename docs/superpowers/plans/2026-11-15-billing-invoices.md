# Billing: Invoices Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Billing module in Quill where a workspace creates, sends and collects on invoices for its clients, with invoice lines as the record of time worked.

**Architecture:** New `Billing` module (capability `invoices`) gated by a new `invoice:manage` permission. Four new models (`BillingSetting`, `WorkType`, `Invoice`, `InvoiceLine`, `InvoicePayment`) scoped to the organization; totals and status live in one method, `Invoice::recalculate()`. Inertia + Vue pages under `{current_organization}/invoices`, a public page at `/invoice/{token}`, and a queued `InvoiceSent` notification.

**Tech Stack:** Laravel 13, Inertia 3 + Vue 3 + shadcn-vue, Wayfinder, Pest, Spatie activitylog + permission.

**Spec:** `docs/superpowers/specs/2026-11-15-billing-invoices-design.md`

## Global Constraints

- Every model has `#[UseFactory]` and `#[ObservedBy]`; no `boot`/`booted` on models — side effects go in observers (`tests/Architecture/ModelArchTest.php`).
- Controllers extend `Controller`, stay under 100 lines, no private/protected methods, no `Request`, `Gate`, `abort*` (`ControllerArchTest`). Authorization lives on routes via `->can(...)` / `middlewareFor`.
- Actions live in `app/Actions/Billing`, expose `handle()`, never use `auth()`/`abort*`.
- Notifications implement `ShouldQueue` and call `$this->afterCommit()`.
- Money columns `decimal(12,2)`, percentages `decimal(5,2)`, quantity `decimal(8,2)`; cast `decimal:2`.
- Invoice number format `{prefix}-{year}-{0001}`, default prefix `INV`, default payment terms 30 days.
- Tax is charged on the subtotal **before** discount. `amount_due = max(0, total − amount_paid)`.
- Every query is scoped to the current organization. Route model binding uses `scopeBindings()` as existing routes do.
- No new composer/npm dependencies. No PDF.
- No form hints/helper text/explanatory placeholders on fields. No prose comments; type docblocks are fine.
- All new models log activity (`LogsActivity`, `logOnlyDirty`) like `Client`.
- Run `vendor/bin/pint --dirty --format agent` before each commit. Tests: `php artisan test --compact <path>`.
- Ship as a stack: this whole plan is layer 1 on branch `feat/billing-invoices`.

## Review Focus

1. **Cross-workspace leakage** — an invoice, line issue, contact, work type or project from another organization submitted by id must be rejected (422), not attached. Tests in Tasks 5, 6, 7.
2. **Editing a sent invoice's money** — a PATCH that changes quantity/rate/tax/discount on a Sent or Paid invoice must be rejected, while description/reference/work type still save. Tests in Task 6.
3. **Concurrent invoice creation** — two creates in the same org/year never get the same number (unique index + lock). Test in Task 4 asserts the unique index exists and sequential creates increment.
4. **Overpayment and payment removal** — overpaying shows amount due $0.00 and Paid; deleting a payment from a Paid sent invoice drops it to Partial/Sent. Tests in Task 4 and 8.
5. **Public link for Draft/Void** — a Draft token is 404; a Void token shows the void notice, never a Pay-able invoice. Tests in Task 9.

---

### Task 1: Billing module and `invoice:manage` permission

**Files:**

- Modify: `app/Enums/OrganizationModule.php`, `app/Enums/OrganizationCapability.php`, `app/Enums/OrganizationPermission.php`
- Modify: `app/Http/Requests/Organizations/UpdateOrganizationModulesRequest.php`, `resources/js/pages/organization/Settings.vue`, `resources/js/components/AppSidebar.vue` (default `modules` fallback only)
- Modify: `database/factories/OrganizationFactory.php`, `database/seeders/OrganizationSeeder.php` (Admin role gets `ManageInvoices`)
- Create: migration `grant_invoice_management_to_owners` (copy `2026_10_02_152114_grant_ticket_management_to_owners.php`, with `ManageInvoices`)
- Test: `tests/Feature/Billing/BillingModuleTest.php`

**Interfaces:**

- Produces: `OrganizationModule::Billing` (`'billing'`, key `'billing'`, capabilities `[Invoices]`); `OrganizationCapability::Invoices` (`'invoices'`, module `Billing`); `OrganizationPermission::ManageInvoices` (`'invoice:manage'`, group `__('Billing')`, label `__('Manage invoices')`, prop key `canManageInvoices`). Module form keys `billing_enabled`, `billing_invoices_enabled`.

- [ ] **Step 1: Write failing tests**

```php
test('billing is on for a workspace with no setting', fn () =>
    expect(Organization::factory()->create()->canUse(OrganizationCapability::Invoices))->toBeTrue());

test('an owner can switch billing off', function () {
    // PATCH organizations.modules.update with all existing keys true + billing_enabled false
    // expect $organization->fresh()->canUse(OrganizationCapability::Invoices)->toBeFalse()
});

test('owners and admins can manage invoices, members cannot', function () {
    // addMember($admin,'Admin'), addMember($member,'Member')
    // expect hasOrganizationPermission(.., ManageInvoices) true / true(owner) / false
});
```

- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Billing/BillingModuleTest.php` — FAIL (undefined case).
- [ ] **Step 3: Add the enum cases, request rules, Settings.vue toggles (same markup as Teams/Feedback), factory/seeder grant, and migration.**
- [ ] **Step 4: Run the test file and `tests/Feature/Organizations` — PASS.**
- [ ] **Step 5: Commit** `Billing becomes a module with its own permission`

---

### Task 2: Billing settings and work types

**Files:**

- Create: migrations `create_billing_settings_table`, `create_work_types_table`
- Create: `app/Models/BillingSetting.php`, `app/Models/WorkType.php`, observers `BillingSettingObserver`, `WorkTypeObserver` (empty bodies are fine if nothing to do — follow `ClientDomainObserver`), factories
- Modify: `app/Models/Organization.php` — `billingSettings(): BillingSetting`, `workTypes(): HasMany`
- Create: `app/Policies/BillingSettingPolicy.php`, `app/Policies/WorkTypePolicy.php` — all abilities = `canUse(Invoices) && hasOrganizationPermission(ManageInvoices)`
- Create: `app/Http/Controllers/Billing/BillingSettingController.php` (`edit`, `update`), `WorkTypeController.php` (`store`, `update`, `destroy`), requests in `app/Http/Requests/Billing/`
- Create: `resources/js/pages/billing/Settings.vue`
- Modify: `routes/web.php` — in the `{current_organization}/settings` group: `GET/PATCH billing` (`organizations.billing.edit|update`), `POST/PATCH/DELETE work-types/{workType}` (`organizations.work-types.*`)
- Test: `tests/Feature/Billing/BillingSettingsTest.php`, `WorkTypeTest.php`

**Interfaces:**

- Produces: `Organization::billingSettings(): BillingSetting` — returns the row, `firstOrCreate` with defaults `invoice_prefix='INV'`, `payment_terms_days=30`. `BillingSetting` fields: `invoice_prefix`, `default_rate` (nullable), `payment_terms_days`, `tax_rate` (nullable), `default_notes`, `default_terms`, `from_address`. `WorkType` fields: `name`, `color` (default `'gray'`), `is_active` (default true), `sort_order`.

- [ ] **Step 1: Failing tests**
    - `billing settings are created with defaults on first access` → prefix `INV`, terms `30`.
    - `a manager updates billing settings` → PATCH sets `default_rate 125.00`, `tax_rate 8.25`, `from_address`.
    - `members without invoice management cannot see billing settings` → GET 403.
    - `billing settings are 403 when billing is switched off`.
    - `work type names are unique within a workspace` → second `Design` → error on `name`; same name in another org OK.
- [ ] **Step 2: Run — FAIL.**
- [ ] **Step 3: Implement.** Unique index `(organization_id)` on `billing_settings`; `(organization_id, name)` on `work_types`. Settings page: one form for settings, a list below to add/rename/deactivate/delete work types. Add a "Billing" link to the organization settings navigation, shown when `canManageInvoices` and the module is on.
- [ ] **Step 4: Run — PASS.**
- [ ] **Step 5: Commit** `Workspaces keep their own billing settings and work types`

---

### Task 3: Billing fields on clients and projects

**Files:**

- Create: migration `add_billing_fields_to_clients_and_projects` — `clients.billing_rate` decimal(12,2) null, `clients.billing_contact_id` FK `client_contacts` nullOnDelete, `clients.billed_through_client_id` FK `clients` nullOnDelete, `projects.billing_rate` null
- Modify: `app/Models/Client.php` — relations `billingContact()`, `billedThrough()`, `billedClients()` (hasMany on `billed_through_client_id`); method `billableClientIds(): Collection<int,int>`; add fields to activity log
- Modify: `app/Models/Project.php` — cast/log `billing_rate`; method `billingRate(): ?string` (project → client → org default)
- Modify: `app/Http/Requests/Clients/ClientRequest.php`, the project update request, `ClientResource`, `ProjectFormResource`, `pages/clients/Edit.vue`, project edit page
- Test: `tests/Feature/Billing/ClientBillingTest.php`

**Interfaces:**

- Produces: `Client::billableClientIds(): Collection` = `[$this->id, ...ids of clients whose billed_through_client_id = $this->id]` (one level). `Project::billingRate(): ?string`.

- [ ] **Step 1: Failing tests**
    - `a manager sets a client's billing rate, contact and billed-through client`.
    - `billing contact must belong to the client` → contact of another client → error.
    - `billed-through client must be in the workspace and not the client itself` → errors.
    - `billing fields are ignored for members without invoice management` → PATCH with `billing_rate` by a client manager lacking `invoice:manage` leaves it null; name still updates.
    - `92 Labs bills KMS Tools work` → `$labs->billableClientIds()` equals `[$labs->id, $kms->id]`.
    - `project rate falls back to client then workspace default` → `150.00`, `125.00`, `100.00`.
- [ ] **Step 2: Run — FAIL.**
- [ ] **Step 3: Implement.** Billing rules are only added to `ClientRequest::rules()` when the user has `ManageInvoices`; the controller keeps using `$request->validated()`. Edit pages show a "Billing" section only when `canManageInvoices`.
- [ ] **Step 4: Run — PASS, plus `tests/Feature/Clients tests/Feature/Projects`.**
- [ ] **Step 5: Commit** `Clients and projects carry billing rates`

---

### Task 4: Invoice, line and payment models with totals, status and numbering

**Files:**

- Create: enums `app/Enums/InvoiceStatus.php` (`Draft, Sent, Partial, Paid, Overdue, Void`, string values lowercase, `label()`), `AdjustmentType.php` (`Percentage='percentage'`, `Fixed='fixed'`), `LineUnit.php` (`Hours='hours'`, `Units='units'`), `PaymentMethod.php` (`Card, Check, Wire, Ach, Cash, Other`, lowercase values, `label()`)
- Create: migrations `create_invoices_table`, `create_invoice_lines_table`, `create_invoice_payments_table` — columns exactly as the spec's Data section
- Create: models `Invoice`, `InvoiceLine`, `InvoicePayment`; observers `InvoiceObserver`, `InvoiceLineObserver`, `InvoicePaymentObserver`; factories (Invoice states `sent()`, `void()`)
- Create: `app/Actions/Billing/CreateInvoice.php`
- Test: `tests/Feature/Billing/InvoiceTotalsTest.php`, `InvoiceStatusTest.php`, `InvoiceNumberTest.php`

**Interfaces:**

- Produces:
    - `Invoice::recalculate(): void` — the only place totals/status are written; no-op when `status === Void`. Computes per the spec's Totals block, rounds to 2, derives status (see below), saves quietly-then-logs once.
    - `Invoice::amountDue(): string` (`max(0, total − amount_paid)`, 2dp), `Invoice::isEditable(): bool` (`sent_at === null && status !== Paid && status !== Void`), `Invoice::publicUrl(): string`.
    - `Invoice` relations: `organization`, `client`, `project`, `sprint`, `contact` (`ClientContact`), `lines` (ordered by `sort_order`), `payments`.
    - `InvoiceLineObserver` / `InvoicePaymentObserver`: on `saving` a line sets `amount = round(quantity × rate, 2)`; on `saved`/`deleted` of either, call `$invoice->recalculate()`.
    - `InvoiceObserver::creating`: sets `public_token = Str::random(40)` when empty.
    - `CreateInvoice::handle(Organization $organization, Client $client, array $attributes): Invoice` — in a transaction, `lockForUpdate()` the org's `BillingSetting`, compute number, create as `Draft` with defaults filled from settings/client (contact = `billing_contact_id`, `tax_rate`, `notes`, `terms`, `issue_date = today`, `due_date = today + payment_terms_days`) unless given in `$attributes`.

Status derivation inside `recalculate()`:

```
if total > 0 and amount_paid >= total → Paid
elseif amount_paid > 0                 → Partial
elseif sent_at is null                 → Draft
elseif status is Overdue               → Overdue (layer 2 owns this)
else                                   → Sent
```

- [ ] **Step 1: Failing tests (exact values)**

```php
// InvoiceTotalsTest
test('totals tax the subtotal before the discount', function () {
    // lines: 10 hours × 100.00, 2.5 hours × 80.00; tax_rate 8.25; discount percentage 10; deposit percentage 50
    expect($invoice->fresh())
        ->subtotal->toBe('1200.00')->tax_amount->toBe('99.00')
        ->discount_amount->toBe('120.00')->total->toBe('1179.00')
        ->deposit_amount->toBe('589.50');
});
test('a fixed discount and deposit are taken as given')    // discount fixed 50 → total 1249.00; deposit fixed 300 → 300.00
test('amount due never goes below zero')                    // pay 1300 on 1179 → amountDue '0.00'
test('a void invoice is never recalculated')                // void, add payment row directly → total/status unchanged

// InvoiceStatusTest
test('a fully paid draft becomes paid')
test('a part-paid draft becomes partial')
test('removing the only payment from an unsent invoice returns it to draft')
test('removing the only payment from a sent invoice returns it to sent')
test('a zero-total sent invoice is not marked paid')

// InvoiceNumberTest  (travel to 2026-11-15)
test('numbers follow the workspace prefix and year')        // first → 'INV-2026-0001'; prefix 'VL' → 'VL-2026-0001'
test('numbers continue from the highest existing one')      // existing 'INV-2026-0041' → 'INV-2026-0042'
test('each workspace numbers on its own')
test('defaults come from the client and billing settings')  // contact, tax 8.25, due = issue + 30
```

- [ ] **Step 2: Run — FAIL.**
- [ ] **Step 3: Implement.** Unique `(organization_id, number)` and unique `public_token`. Number = parse the numeric suffix of the max `number` starting with `{prefix}-{year}-` in the org, +1, pad 4.
- [ ] **Step 4: Run — PASS, and `tests/Architecture`.**
- [ ] **Step 5: Commit** `Invoices total themselves the way Atlas does`

---

### Task 5: Invoice index, create, view/edit header, delete

**Files:**

- Create: `app/Policies/InvoicePolicy.php` — `viewAny/create(User, Organization)`, `view/update/delete(User, Invoice)`; all require `canUse(Invoices)` + `ManageInvoices`; `update` also requires status ≠ Void; `delete` requires status Draft and no payments
- Create: `app/Http/Controllers/Billing/InvoiceController.php` (`index`, `store`, `show`, `update`, `destroy`), `app/Http/Requests/Billing/StoreInvoiceRequest.php`, `UpdateInvoiceRequest.php`
- Create: `app/Http/Resources/InvoiceResource.php`, `InvoiceListResource.php`
- Create: `resources/js/pages/invoices/Index.vue`, `resources/js/pages/invoices/Show.vue`, `resources/js/components/CreateInvoiceModal.vue`, `InvoiceStatusBadge.vue`
- Modify: `routes/web.php` — `Route::resource('invoices', InvoiceController::class)->only([...])` in the org group with `middlewareFor` like clients; `AppSidebar.vue` — "Billing" group with "Invoices" when module + `canManageInvoices`
- Test: `tests/Feature/Billing/InvoiceTest.php`

**Interfaces:**

- Consumes: `CreateInvoice::handle`, `Invoice::isEditable()`, `Client::billableClientIds()`, `Project::billingRate()`.
- Produces: routes `invoices.index|store|show|update|destroy` (bound by `id`, not number). `InvoiceResource` includes `amountDue`, `isEditable`, `publicUrl`, lines, payments, and option lists (contacts of the client, billable projects, sprints, work types).

- [ ] **Step 1: Failing tests**
    - `a manager sees the workspace's invoices only`.
    - `a manager creates an invoice for a client` → redirects to show; status Draft.
    - `starting from a project bills its billed-through client` → POST `project_id` of KMS Tools, no client → `client_id` is 92 Labs.
    - `project must be billable to the invoice's client` → project of an unrelated client → error `project_id`.
    - `contact must belong to the invoice's client`; `cc_emails` each must be an email.
    - `money fields cannot change once sent` → PATCH `tax_rate` on sent → error; PATCH `notes` → saves.
    - `drafts can be deleted, sent invoices cannot` → 403.
    - `invoices from another workspace are 404`.
    - `members without invoice management get 403`.
- [ ] **Step 2: Run — FAIL.**
- [ ] **Step 3: Implement.** `UpdateInvoiceRequest` drops money/date-range rules for `tax_rate`, `discount_*`, `deposit_*` when `! $invoice->isEditable()` and rejects them with a message `__('This invoice has been sent; its amounts can no longer change.')`. After update call `recalculate()`. Index: columns number, client, issue date, due date, total, amount due, status; filter by status and client via query string.
- [ ] **Step 4: Run — PASS.**
- [ ] **Step 5: Commit** `Invoices can be created and edited`

---

### Task 6: Invoice lines with issue references

**Files:**

- Create: `app/Http/Controllers/Billing/InvoiceLineController.php` (`store`, `update`, `destroy`), `InvoiceLineOrderController.php` (`update`), requests `InvoiceLineRequest.php`, `InvoiceLineOrderRequest.php`
- Modify: `app/Models/Issue.php` — `matching()` gains optional `?Collection $clientIds = null`; when given, restrict `project_id` to projects whose `client_id` is in it
- Modify: `app/Http/Requests/Issues/IssueLookupRequest.php` — optional `invoice` id → resolves to `invoice->client->billableClientIds()` (invoice must be in the org)
- Modify: `resources/js/pages/invoices/Show.vue` — lines table (description, issue picker reusing the existing lookup component, reference link, work type, quantity, unit, rate, amount; drag reorder via existing row-drag composable)
- Test: `tests/Feature/Billing/InvoiceLineTest.php`, add to `tests/Feature/Issues/IssueLookupTest.php` (or the existing lookup test file)

**Interfaces:**

- Consumes: `Invoice::isEditable()`, `Client::billableClientIds()`.
- Produces: routes `invoices.lines.store|update|destroy`, `invoices.lines.order.update`.

- [ ] **Step 1: Failing tests**
    - `a line for hours gets amount from quantity and rate` → 2.5 × 120 → `300.00`; invoice subtotal updates.
    - `a new line defaults its rate from the project then client then workspace`.
    - `an issue line takes the issue title when description is blank`.
    - `issue must belong to a client billed on this invoice` → KMS issue on 92 Labs invoice OK; unrelated client's issue → error `issue_id`.
    - `work type must be in the workspace`.
    - `reference_url must be a url up to 2048 chars`.
    - `a sent invoice's line keeps its money` → PATCH quantity → error; PATCH description/reference_url/work_type_id → saves. Adding or deleting lines on a sent invoice → 403.
    - `lines can be reordered`.
    - `issue lookup for an invoice only returns that invoice's billable issues`.
- [ ] **Step 2: Run — FAIL.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run — PASS.**
- [ ] **Step 5: Commit** `Invoice lines record time against issues`

---

### Task 7: Payments

**Files:**

- Create: `app/Http/Controllers/Billing/InvoicePaymentController.php` (`store`, `update`, `destroy`), `app/Http/Requests/Billing/InvoicePaymentRequest.php`
- Modify: `resources/js/pages/invoices/Show.vue` — payments list + "Record payment" dialog
- Test: `tests/Feature/Billing/InvoicePaymentTest.php`

**Interfaces:**

- Produces: routes `invoices.payments.store|update|destroy`, gated by `can:update,invoice` (which already excludes Void).

- [ ] **Step 1: Failing tests**
    - `recording a full payment on a sent invoice marks it paid` → status Paid, amountDue `0.00`.
    - `a deposit can be recorded on a draft` → status Partial, `isEditable()` still true (`sent_at` null, not Paid).
    - `deleting a payment from a paid invoice reopens it` → Partial or Sent.
    - `payments cannot be added to a void invoice` → 403.
    - `amount must be positive; method must be a PaymentMethod; paid_on a date`.
- [ ] **Step 2–4: Run FAIL → implement → PASS.**
- [ ] **Step 5: Commit** `Payments are recorded against invoices`

---

### Task 8: Send, void and duplicate

**Files:**

- Create: `app/Actions/Billing/SendInvoice.php`, `VoidInvoice.php`, `DuplicateInvoice.php`
- Create: `app/Http/Controllers/Billing/InvoiceSendController.php`, `InvoiceVoidController.php`, `InvoiceDuplicateController.php` (single `store` each)
- Create: `app/Notifications/InvoiceSent.php`, `resources/views/mail/invoice-sent.blade.php`, `invoice-sent-text.blade.php`
- Modify: policy — `send(User, Invoice)` (status Draft, has a contact, has ≥1 line), `void(User, Invoice)` (status not Draft/Paid/Void)
- Test: `tests/Feature/Billing/SendInvoiceTest.php`, `VoidInvoiceTest.php`, `DuplicateInvoiceTest.php`

**Interfaces:**

- Produces: `SendInvoice::handle(Invoice): void` — sets `sent_at = now()`, recalculates (Draft → Sent, or Paid/Partial if already paid), notifies `$invoice->contact` with `InvoiceSent` and CCs `cc_emails` on the mail message. `VoidInvoice::handle(Invoice): void` — `status = Void`, `voided_at = now()`. `DuplicateInvoice::handle(Invoice): Invoice` — via `CreateInvoice`, copies client, project, contact, cc, tax/discount/deposit settings, notes, terms and lines (with `issue_id`, `reference_url`, `work_type_id`), new number, today's dates, no payments, no sprint/service period.
- Mail subject: `__('Invoice :number from :organization', ...)`; body shows total, due date, amount due and a button to `publicUrl()`. From = the app's normal mail sender, organization name as from-name.

- [ ] **Step 1: Failing tests** (`Notification::fake()`)
    - `sending emails the contact with the cc list and marks it sent`.
    - `an invoice with no contact or no lines cannot be sent` → 403.
    - `a sent invoice can be voided and becomes read-only` → later PATCH notes → 403.
    - `a paid invoice cannot be voided`.
    - `duplicating copies lines into a new draft` → new number, Draft, same 2 lines, no payments.
- [ ] **Step 2–4: Run FAIL → implement → PASS (incl. `tests/Architecture`).**
- [ ] **Step 5: Commit** `Invoices can be sent, voided and duplicated`

---

### Task 9: Public invoice page

**Files:**

- Create: `app/Http/Controllers/Billing/PublicInvoiceController.php` (`show`), `app/Http/Resources/PublicInvoiceResource.php`
- Create: `resources/js/pages/invoices/Public.vue` (guest layout, print stylesheet: hides chrome, black on white)
- Modify: `routes/web.php` — `Route::get('invoice/{invoice:public_token}', ...)->name('invoices.public')` outside the org prefix, no auth
- Test: `tests/Feature/Billing/PublicInvoiceTest.php`

**Interfaces:**

- Consumes: `Invoice::publicUrl()` returns `route('invoices.public', $this->public_token)`.
- `PublicInvoiceResource` exposes only what the client sees: organization name/logo/`from_address`, client name, contact, number, dates, service period, lines (description, issue reference via `Issue::reference()`, reference_url, quantity, unit, rate, amount), totals, deposit, payments total, amount due, notes, terms, `isVoid`. No ids, no internal fields.

- [ ] **Step 1: Failing tests**
    - `anyone with the link sees a sent invoice` → guest 200, Inertia component `invoices/Public`, has number and amountDue.
    - `a draft link is not found` → 404.
    - `a void link shows that the invoice is void` → `isVoid` true.
    - `the public page leaks nothing internal` → props have no `id`, `organization_id`, `client_id`.
- [ ] **Step 2–4: Run FAIL → implement → PASS.**
- [ ] **Step 5: Commit** `Clients open invoices from a public link`

---

### Task 10: Finish

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent`
- [ ] **Step 2:** `php artisan test --compact` — whole suite PASS.
- [ ] **Step 3:** `npm run build` and `npm run types` (or the project's type-check script from `package.json`) — no errors.
- [ ] **Step 4:** In the browser at the Herd URL: switch Billing on, set billing settings, create an invoice for a client, add two lines (one with an issue), record a deposit, send it (check Herd mail), open the public link, void it. Fix anything broken.
- [ ] **Step 5: Commit** any fixes; push and open the PR as the bottom of the billing stack.
