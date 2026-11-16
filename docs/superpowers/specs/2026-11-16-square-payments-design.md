# Billing: Square payments

Layer 3 of Quill billing (layer 1: invoices, layer 2: cycles). A client opens the public
invoice link and pays online through Square's hosted checkout; Quill records the payment and the
invoice's status follows.

## Intent

- Clients pay by check, card, ACH (bank transfer, Square, Cash App) or Zelle. Card and bank
  payments should happen from the invoice link without Karl in the loop, and land on the invoice
  by themselves. Checks and Zelle stay manual, but the invoice tells the client how to send them.
- Atlas did this with an embedded card form and no webhook. Quill uses Square's hosted checkout
  and a webhook so a payment is never lost to a closed browser.

### Goals

- A "Pay" button on the public invoice page that charges the full amount due through Square
  hosted checkout (card, ACH bank transfer, Cash App Pay, Apple Pay, Google Pay — whatever the
  Square account has enabled).
- Payments recorded automatically, exactly once, with the right method and Square's payment ID.
- "Other ways to pay" instructions (Zelle, check address) printed on the public page.
- Square credentials per workspace, with a sandbox mode for testing.

### Non-goals

- Deposits or client-chosen amounts. Online payment is always the full amount due. Deposits are
  rare and handled by hand (Karl).
- Refunds, disputes and fees. A refund is handled in Square; Karl deletes or edits the payment in
  Quill by hand.
- Stripe, Square's embedded payment form, Square Invoices.
- Recording Zelle or check payments automatically.

### Anti-goals

- A payment recorded twice (webhook and return both firing), or not at all.
- A client charged an out-of-date amount because an old checkout link was reused.
- Secrets (access token, webhook signature key) readable in the UI or logs after saving.

## Approaches considered

1. **Hosted checkout (chosen).** Quill creates a Square payment link for the current amount due
   when the client clicks Pay, redirects there, and records the payment from Square's webhook and
   from the return visit. Least code, most payment methods, Square owns the payment UI.
2. Embedded Web Payments SDK (Atlas's way). Client stays on the page, but Quill builds and
   maintains the form per payment method; no Apple/Google Pay without extra work.

Square is called with Laravel's HTTP client, not the `square/square` SDK, to avoid a new
dependency; only a handful of endpoints are needed. _(My call.)_

## Data

### `billing_settings` additions

- `square_environment` — `sandbox` | `production`, default `sandbox`.
- `square_access_token` — encrypted cast, nullable. Never sent to the browser; the form shows
  "Saved" and a replace field.
- `square_location_id` — nullable string.
- `square_webhook_signature_key` — encrypted cast, nullable. Same handling as the token.
- `square_webhook_token` — random string, generated on first save; part of the webhook URL.
- `payment_instructions` — nullable text. Free text printed under "Other ways to pay" (Zelle
  handle, check mailing address). _(My call: one text block instead of separate Zelle/check
  fields; Karl writes it once.)_

Square is "configured" for a workspace when token and location are set. Online payment shows
only when configured.

### `square_checkouts` (new)

`invoice_id` (cascade), `payment_link_id`, `order_id` (unique), `url`, `amount` decimal(12,2),
`status` (`open`, `pending`, `paid`, `cancelled`), `square_payment_id` nullable, timestamps. One
row per Pay click. `pending` = Square reported a payment for it that hasn't completed (ACH in
flight).

### `invoice_payments` changes

- `PaymentMethod` gains `zelle` and `cash_app` (labels "Zelle", "Cash App").
- `square_payment_id` — nullable, unique. The idempotency key for recording.

## Paying

1. The public page (`/invoice/{token}`) shows **Pay $X** when: Square is configured, the invoice
   is sent, not Void, not Paid (so Sent, Partial and Overdue all show it), amount due > 0, and no
   checkout for it is `pending`. While one is pending the page shows "Your payment is
   processing" instead of Pay, so an ACH client can't pay twice.
2. Clicking it posts to `POST /invoice/{token}/pay`. Quill:
    - marks any earlier `open` checkout for this invoice `cancelled` and deletes its Square payment
      link (best effort), so an old amount can't be paid;
    - creates a payment link (`POST /v2/online-checkout/payment-links`, `quick_pay` with name
      "Invoice INV-…", the amount due in cents, USD, the location; `checkout_options.redirect_url`
      = the public invoice URL; `payment_note` = the invoice number; Apple Pay, Google Pay and
      Cash App Pay enabled), stores the `square_checkouts` row, and redirects to its URL.
    - On a Square error: back to the invoice page with "Online payment isn't available right now.
      Please try again or use another way to pay."
3. Square redirects the client back to the public page with `orderId` in the query string. Only
   on a visit carrying an `orderId` that matches one of this invoice's `open` checkouts, Quill
   reads the order (`GET /v2/orders/{id}`), takes its tender payment IDs, fetches each payment
   (`GET /v2/payments/{id}`) and passes it to the same recorder as the webhook. Plain visits make
   no Square calls. The page then shows Paid / Partial as usual, or "Your payment is processing"
   while pending.

### Stale checkouts _(my calls)_

- Open checkouts are cancelled (and their links deleted, best effort) whenever the amount due can
  change: a payment recorded or edited by hand, a line or money field changed, the invoice voided,
  a new Pay click, or the Square environment switched.
- If a stale link is paid anyway, the money is recorded as received (Square's amount), even if it
  overpays; the notification says so ("…overpaid by $X").

## Recording — `RecordSquarePayment::handle(Organization $organization, array $payment): ?InvoicePayment`

Called by the webhook and the return visit with a Square Payment object.

- Find the checkout by `order_id` within the workspace; unknown order → ignore.
- Checkout already `paid` → do nothing. This keeps a payment Karl deleted by hand (after a refund
  in Square) from being re-created by a later `payment.updated`.
- `status = PENDING` → mark the checkout `pending` with the payment ID; record nothing yet.
- `status = COMPLETED` → record (below). Any other status (FAILED, CANCELED) on a `pending`
  checkout → back to `open`.
- `square_payment_id` already recorded → return it (unique index as the last guard).
- Create the payment: amount = Square `amount_money` (cents → dollars, gross, fees ignored),
  `paid_on` = the payment's created date in the billing timezone, method by `source_type`:
  `CARD` (including Apple Pay and Google Pay) → card, `BANK_ACCOUNT` → ach, `WALLET` with
  `wallet_details.brand = CASH_APP` → cash_app, anything else → other;
  `reference` = the Square payment ID. Mark the checkout `paid`. Recalculate (layer 1), so status
  becomes Paid or Partial, and Overdue clears.
- Notify the workspace's invoice managers: `InvoicePaymentReceived` (mail + database, queued):
  "Hamill Inc paid $1,299.00 on INV-2026-0001 by card."
- Works on a Void invoice too (money arrived; Karl sorts it out), but the notification says the
  invoice is void.

## Webhook

`POST /webhooks/square/{square_webhook_token}` — outside auth and CSRF. Verifies
`x-square-hmacsha256-signature` = base64(HMAC-SHA256(signature key, notification URL + raw body)).
The notification URL in the signature is built from `config('app.url')` plus the route path, so
proxies can't change it; it is the same URL the settings page shows. Bad signature → 403.
Sandbox and production need their own webhook subscription and signature key in Square; the
settings page says so next to the environment switch. Handles `payment.created` and `payment.updated`. Unknown token → 404; ignored event types → 200. If recording throws, the error is reported
and the webhook answers 500 so Square retries; recording is idempotent, so a retry is safe. The settings page shows the exact webhook URL to paste into Square's dashboard.

## Screens

- **Billing settings → Online payments (Square):** environment (Sandbox / Production), access
  token (password field; "Saved" when set, empty input replaces it), location ID, webhook
  signature key (same as token), the webhook URL with a copy button, and "Other ways to pay"
  (textarea → `payment_instructions`).
- **Public invoice page:** a Pay section above the totals when online payment applies; a "Paid —
  thank you" banner when paid; "Your payment is processing" when the latest checkout's payment is
  pending; "Other ways to pay" block when `payment_instructions` is set (shown on unpaid
  invoices). Print stylesheet hides the Pay button.
- **Invoice page (internal):** payments recorded from Square show the method and the Square
  payment ID as the reference, like manual ones. No new UI.

## Permissions

Square settings use the existing `invoice:manage` permission and live on the billing settings
page. The pay and webhook routes are public by design: pay is scoped by the invoice's public
token, the webhook by its secret token plus signature.

## Left to the builder

- Exact Square API request shapes, checked against Square's current docs (API version header).
- How the HTTP calls are faked in tests (`Http::fake`).
- Notification copy beyond what's given.
