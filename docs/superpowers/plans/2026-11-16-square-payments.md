# Square Payments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Clients pay an invoice's full amount due through Square hosted checkout from the public invoice page, and Quill records each payment exactly once.

**Architecture:** Per-workspace Square settings on `billing_settings`. A thin `SquareClient` (Laravel HTTP client) wraps the five Square calls. `StartSquareCheckout` creates a payment link per Pay click; `RecordSquarePayment` is the single recorder, fed by a signed webhook and by the client's return visit. An observer cancels open checkouts whenever the amount due can change.

**Tech Stack:** Laravel 13, Pest (`Http::fake`), Inertia 3 + Vue 3 + shadcn-vue, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-11-16-square-payments-design.md`

## Global Constraints

- Work on `main`; commit per task; do not push. No new composer or npm dependencies (Square via `Http`).
- Square base URLs: sandbox `https://connect.squareupsandbox.com`, production `https://connect.squareup.com`; send `Square-Version` header (a current dated version, one constant) and `Authorization: Bearer {token}`.
- Secrets (`square_access_token`, `square_webhook_signature_key`) use the `encrypted` cast, are never in an Inertia prop, and never logged.
- Webhook signature: base64(HMAC-SHA256(signature key, notification URL + raw body)) compared with `x-square-hmacsha256-signature` using `hash_equals`; notification URL = `rtrim(config('app.url'), '/')` + the route path.
- Money: cents ↔ dollars with integer math (`(int) round($dollars * 100)`), USD only.
- Arch rules (tests/Architecture): `#[UseFactory]`/`#[ObservedBy]` on models; controllers < 100 lines, no private/protected methods, no Request/Gate/abort; actions in `App\Actions\Billing` with `handle()`; notifications `ShouldQueue` + `via()`; helpers without `handle()` go in `App\Support` (exists). No new top-level app directories.
- Vue: single quotes, 4-space indent, never split a closing tag; `npm run check:fix` before committing Vue.
- PHP: pint; `composer types:check` (PHPStan) 0 errors; `composer ide-helper` after new columns.

## Review Focus

1. Webhook and return visit for the same payment arriving together → one InvoicePayment — pinned in Task 4.
2. Karl deletes a Square payment after a refund; a later `payment.updated` must not re-create it — pinned in Task 4.
3. A forged or unsigned webhook, or a valid one for another workspace's order → nothing recorded — pinned in Task 4.
4. Pending ACH: Pay hidden, "processing" shown, then recorded on completion — pinned in Tasks 3 and 4.
5. Access token or signature key leaking into page props — pinned in Task 1.

---

### Task 1: Settings, payment methods, checkouts table

**Files:**

- Create: migrations (billing_settings Square columns; invoice_payments `square_payment_id` unique nullable; `square_checkouts`), `app/Models/SquareCheckout.php` (+ factory, observer), `app/Enums/SquareCheckoutStatus.php`
- Modify: `app/Enums/PaymentMethod.php` (add `Zelle = 'zelle'` label "Zelle", `CashApp = 'cash_app'` label "Cash App"), `app/Models/BillingSetting.php`, `app/Models/Invoice.php` (`squareCheckouts()` HasMany), `app/Http/Requests/Billing/BillingSettingRequest.php`, `app/Http/Controllers/Billing/BillingSettingController.php`, `resources/js/pages/billing/Settings.vue`, its TS type
- Test: `tests/Feature/Billing/BillingSettingsTest.php`

**Interfaces:**

- Produces: `BillingSetting` attributes `square_environment` ('sandbox'|'production', default 'sandbox'), `square_access_token` (encrypted), `square_location_id`, `square_webhook_signature_key` (encrypted), `square_webhook_token` (random 40 chars, created when missing), `payment_instructions`; `BillingSetting::squareConfigured(): bool` (token and location set); `SquareCheckout` (`invoice_id`, `payment_link_id`, `order_id` unique, `url`, `amount` decimal:2, `status` SquareCheckoutStatus Open/Pending/Paid/Cancelled, `square_payment_id`).
- Edit payload `square`: `{environment, hasAccessToken: bool, locationId, hasSignatureKey: bool, webhookUrl}` plus `paymentInstructions`. Empty token/key inputs keep the saved value; a non-empty one replaces it.

- [ ] **Step 1: Failing tests**
    - `square settings save and secrets stay hidden`: PATCH environment production, token 'EAAA-secret', location 'L1', key 'sig-secret', instructions "Zelle: pay@x.test" → stored (token decrypts to 'EAAA-secret'); GET edit → props contain `hasAccessToken` true and the page JSON does not contain 'EAAA-secret' or 'sig-secret'.
    - `blank secret inputs keep the saved ones`.
    - `the webhook url is shown and stable`: equals `config('app.url').'/webhooks/square/'.$token`; second GET same token.
    - `environment must be sandbox or production`.
    - `members without invoice:manage cannot change square settings` (follow existing BillingSettingsTest pattern).
    - `payment methods include Zelle and Cash App` (labels).
- [ ] **Step 2: Run, expect failures.**
- [ ] **Step 3: Implement.** Settings.vue gets an "Online payments (Square)" section: environment select with the note "Sandbox and production each need their own webhook and signature key in Square.", password inputs with "Saved" hint when set, location ID, webhook URL read-only + Copy, "Other ways to pay" textarea.
- [ ] **Step 4: Run** BillingSettingsTest + `tests/Feature/Billing` + `tests/Architecture`; pint, `composer types:check`, `npm run check`, `npm run types:check`, `npm run build`.
- [ ] **Step 5: Commit** "Workspaces can connect Square".

### Task 2: Square client

**Files:**

- Create: `app/Support/SquareClient.php`
- Test: `tests/Feature/Billing/SquareClientTest.php`

**Interfaces:**

- Consumes: `BillingSetting` Square attributes.
- Produces: `SquareClient::for(BillingSetting $settings): self`; `createPaymentLink(string $name, int $amountCents, string $redirectUrl, string $note): array{id: string, url: string, order_id: string}`; `deletePaymentLink(string $id): void` (swallows 404); `retrieveOrder(string $orderId): array` (decoded `order`); `retrievePayment(string $paymentId): array` (decoded `payment`); `static verifySignature(string $signatureKey, string $notificationUrl, string $body, ?string $signature): bool`. Non-2xx → `App\Support\SquareException` (extends RuntimeException; message without secrets).

- [ ] **Step 1: Failing tests** (`Http::fake` + `Http::assertSent`)
    - `it creates a payment link`: sandbox base URL, POST `/v2/online-checkout/payment-links`, body has `idempotency_key`, `quick_pay.name` "Invoice INV-2026-0001", `quick_pay.price_money` {amount 129900, currency USD}, `quick_pay.location_id` L1, `checkout_options.redirect_url`, `checkout_options.accepted_payment_methods` apple_pay/google_pay/cash_app_pay true, `payment_note`; bearer token; returns id/url/order_id from `payment_link`.
    - `production uses the production host`.
    - `errors raise SquareException without the token in the message`.
    - `deleting a missing link is fine` (404 → no exception).
    - `signatures verify`: known key/url/body → computed signature true; tampered body false; null false.
- [ ] **Step 2–4: Run red, implement, run green; pint, types:check.**
- [ ] **Step 5: Commit** "Quill can talk to Square".

### Task 3: Paying from the public page

**Files:**

- Create: `app/Actions/Billing/StartSquareCheckout.php`, `app/Actions/Billing/CancelSquareCheckouts.php`, `app/Http/Controllers/Billing/PublicInvoicePaymentController.php`
- Modify: `routes/web.php` (`POST invoice/{invoice:public_token}/pay` → `invoices.public.pay`, throttled `throttle:10,1`), `app/Http/Controllers/Billing/PublicInvoiceController.php` (return-visit reconcile when `orderId` query matches an open/pending checkout), `app/Http/Resources/PublicInvoiceResource.php`, `resources/js/pages/invoices/Public.vue`, its TS type
- Test: `tests/Feature/Billing/SquarePaymentTest.php`

**Interfaces:**

- Consumes: `SquareClient`, `SquareCheckout`. (The return-visit reconcile belongs to Task 4, which owns the recorder.)
- Produces: `StartSquareCheckout::handle(Invoice $invoice): SquareCheckout` (cancels other open checkouts via `CancelSquareCheckouts`, creates link for `amountDue()`, stores row Open); `CancelSquareCheckouts::handle(Invoice $invoice): void` (marks Open rows Cancelled, deletes their links, ignores Square errors). Public resource adds `canPayOnline` (configured, sent, not Void/Paid, amountDue > 0, no Pending checkout), `paymentProcessing` (a Pending checkout exists), `paymentInstructions`, `amountDue`.

- [ ] **Step 1: Failing tests**
    - `a client starts a checkout for the amount due`: sent invoice, due 1299.00, configured → POST pay redirects to the faked link URL; a SquareCheckout row Open with amount 1299.00 and order_id.
    - `starting again cancels the earlier checkout and deletes its link` (assertSent DELETE).
    - `pay is refused for drafts, void, paid, zero due, unconfigured, or pending` → redirect back to the public page with the error flash, no Square call.
    - `a Square error sends the client back with a message`: "Online payment isn't available right now. Please try again or use another way to pay."
    - `the public page offers pay only when it applies` (`canPayOnline` true for Sent, Partial, Overdue; false otherwise) and `shows processing while a payment is pending`.
    - `payment instructions show on unpaid invoices`.
- [ ] **Step 2–4: Red, implement, green.** Public.vue: Pay section above totals ("Pay {amount}" button, form POST), "Your payment is processing" notice, "Paid — thank you" banner when Paid, "Other ways to pay" block (whitespace-pre-line) when instructions and not Paid; `print:hidden` on the Pay section. Run Billing + Architecture tests, pint, types:check, npm check/types/build.
- [ ] **Step 5: Commit** "Clients can pay an invoice online".

### Task 4: Recording payments (webhook + return visit)

**Files:**

- Create: `app/Actions/Billing/RecordSquarePayment.php`, `app/Http/Controllers/Billing/SquareWebhookController.php`, `app/Notifications/InvoicePaymentReceived.php`
- Modify: `routes/web.php` (`POST webhooks/square/{token}` → `webhooks.square`, excluded from CSRF in `bootstrap/app.php`), `app/Http/Controllers/Billing/PublicInvoiceController.php` (return-visit reconcile), `app/Http/Requests/...` if a request class is used (must define `rules()`)
- Test: `tests/Feature/Billing/RecordSquarePaymentTest.php`, `tests/Feature/Billing/SquareWebhookTest.php`

**Interfaces:**

- Consumes: `SquareClient`, `SquareCheckout`, `Invoice::recalculate()`, `BillingEligibility` (invoice managers), `Organization::billingTimezone()`.
- Produces: `RecordSquarePayment::handle(Organization $organization, array $payment): ?InvoicePayment` — rules exactly as the spec's Recording section (checkout lookup by `order_id` within the workspace; Paid checkout → nothing; PENDING → checkout Pending; FAILED/CANCELED on Pending → Open; COMPLETED → payment with amount from `amount_money`, `paid_on` = created date in billing timezone, method mapping CARD→card, BANK_ACCOUNT→ach, WALLET+CASH_APP→cash_app, else other, `reference` and `square_payment_id` = payment id; checkout Paid; recalculate; notify). Runs in a transaction locking the checkout row.

- [ ] **Step 1: Failing tests**
    - Recorder: `a completed card payment is recorded once` (call twice → 1 payment, invoice Paid, notification once); `ACH pending then completed` (Pending → no payment, checkout Pending; then COMPLETED → payment method ach); `a failed ACH reopens the checkout`; `Cash App maps to cash_app, Apple Pay card to card, other wallets to other`; `a deleted payment is not re-created` (record, delete payment, call again with COMPLETED → still 0 payments); `another workspace's order is ignored`; `an overpayment is recorded and the notice says so`; `a payment on a void invoice is recorded and flagged`; `partial amount leaves the invoice Partial`; `paid_on uses the billing timezone`.
    - Webhook: `a signed payment.updated is recorded` (signature from the stored key and `config('app.url').'/webhooks/square/'.$token`); `bad or missing signature → 403, nothing recorded`; `unknown token → 404`; `other event types → 200, ignored`; `a recording error still answers 200 and is logged`.
    - Return visit: `returning with orderId records the completed payment` (fake GET order with tender id, GET payment COMPLETED → payment exists, page shows Paid); `a plain visit makes no Square calls` (`Http::assertNothingSent`); `an orderId for another invoice is ignored`.
- [ ] **Step 2–4: Red, implement, green;** Billing + Architecture tests, pint, types:check.
- [ ] **Step 5: Commit** "Square payments land on the invoice by themselves".

### Task 5: Stale checkouts

**Files:**

- Modify: `app/Observers/InvoiceObserver.php` (`updated`: when `total` or `amount_paid` changed, or status became Void → `CancelSquareCheckouts`), `app/Http/Controllers/Billing/BillingSettingController.php` or its action (environment switched → cancel every open checkout in the workspace)
- Test: `tests/Feature/Billing/SquareCheckoutStaleTest.php`

- [ ] **Step 1: Failing tests** (Http::fake)
    - `recording a manual payment cancels open checkouts` (Sent invoice); `changing the total cancels them` (update `total` via a line edit on an invoice whose open checkout exists — set up with the factory directly); `voiding cancels them`; `switching environment cancels every open checkout in the workspace only`; `a Square-recorded payment cancels the other open checkouts but not its own` (own is Paid).
- [ ] **Step 2–4: Red, implement, green;** full suite once; pint, types:check.
- [ ] **Step 5: Commit** "Old checkout links stop working when the amount changes".

### Task 6: Verification

- [ ] Full suite, pint, `composer types:check`, `npm run check`, `npm run types:check`, `npm run build`; migrate local DB.
- [ ] Browser (generated workspace, not VheissuLabs/NotaryDash): billing settings shows the Square section and webhook URL and never the saved token; public page of a sent invoice with fake config shows Pay and "Other ways to pay". No real Square calls without Karl's sandbox credentials — note that the live sandbox run is Karl's to do.
