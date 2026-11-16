# Billing: cycles

Layer 2 of Quill billing (layer 1: `2026-11-15-billing-invoices-design.md`). Each client can be
billed automatically on its own pay period. Quill keeps one Draft open for the client's current
period, sends it at the end of the period, and marks unpaid invoices overdue.

## Intent

- Invoices that line up with each client's pay period without being assembled by hand
  afterwards. Atlas already does this; this layer is what lets Atlas be switched off.
- Each client pays on its own schedule. Today: NotaryDash LLC every two weeks, Saturday to
  Friday; 92 Labs LLC every calendar month. Other clients will have other schedules, so the
  schedule is set on the client.
- Time is tracked on the invoice (layer 1). Logging time means adding a line to the client's
  open cycle Draft. This layer guarantees that Draft exists; layer 4's `log-time` writes to it.

### Goals

- A billing cycle on the client: none, weekly, every two weeks, or calendar month.
- A Draft per client per period, opened automatically, sent automatically at period end.
- A reminder to invoice managers on the last afternoon of a period with lines on it.
- Empty periods disappear quietly.
- Invoices past due are marked Overdue.

### Non-goals

- **Recurring invoices** (copy an invoice on a schedule). Atlas's last recurring invoice was
  replaced by the 92 Labs monthly cycle. *(My call: Karl didn't name any fixed recurring billing
  left. Easy to add later on top of Duplicate.)*
- Payment reminder emails to clients. Overdue is a status in Quill, not an email.
- Card payments (layer 3), MCP tools (layer 4).
- Driving periods from sprints. Considered and dropped: one board's sprints are shared by every
  client on it, but clients pay on different schedules.

### Anti-goals

- Two invoices for the same client and period, or an invoice sent twice, however the job is
  run (late, twice, overlapping).
- Moving lines that were already logged when a client's cycle changes.
- An email for an empty period.

## Approaches considered

1. **Scheduled job (chosen).** `billing:run` runs every minute. Each run opens drafts for
   periods that have started, sends reminders, closes finished periods and marks overdue
   invoices. Every step decides from dates and what already exists, so it's idempotent. Proven
   in Atlas.
2. Open drafts lazily on first logged time, job for the rest. Saves little; "where's this
   period's invoice?" becomes less predictable.
3. Delayed queued send per draft. Precise but breaks silently when a client's cycle or terms
   change, or the queue is down.

## Data

### `clients` additions

- `billing_cycle` — `BillingCycle` enum, nullable (`weekly`, `biweekly`, `monthly`). Null = no
  automatic billing; invoices are made by hand as in layer 1.
- `billing_cycle_starts_on` — date, nullable. The day the schedule counts from. Defaults to the
  day auto billing is turned on, editable. The first period starts that day; nothing is
  back-dated. Monthly ignores it (calendar months). An early send moves it (see Sending early).
- `billing_cc_emails` — json array, nullable. Copied onto each cycle invoice.
- `payment_terms_days` — unsigned int, nullable. Falls back to the workspace's
  `billing_settings.payment_terms_days`. NotaryDash 7, 92 Labs 1.
- `billing_opened_through` — date, nullable. End date of the last period a cycle Draft was
  opened for. Internal; see Opening.

A client billed through another (`billed_through_client_id` set) cannot have a cycle; its work
lands on the bill-to client's Draft. Validation refuses a cycle on such a client, and refuses
setting "billed through" while the client has a cycle (turn the cycle off first).

**Invariant: a client has at most one unsent cycle Draft at a time.** Logging time (layer 4)
always goes to that Draft.

### `invoices` additions

- `closes_at` — timestamp, nullable. Set only on cycle invoices: the moment it is sent
  (11:59 PM on the period's last day, owner's timezone, stored UTC). Null = a manual invoice;
  the job never touches those.
- `reminded_at` — timestamp, nullable.

### Periods

`BillingCycle::periodContaining(CarbonImmutable $day, ?CarbonImmutable $startsOn): array{start, end}`

- weekly: 7-day blocks counted forward from `starts_on`.
- biweekly: 14-day blocks counted forward from `starts_on`.
- monthly: the calendar month of `$day`.
- A day before `starts_on` has no period.

### Timezone

All "day" and "time" decisions use the timezone of the workspace's first owner (earliest member
with the Owner role), falling back to `config('app.timezone')`. *(My call: Quill has no workspace timezone; Karl is the owner of every
workspace that bills.)*

## The job — `billing:run`, every minute, `withoutOverlapping`

Runs only for workspaces where the Billing module and the new `BillingCycles` capability are on.
Each step is its own action so tests and layer 4 can call them.

### 1. Opening — `OpenBillingPeriod::handle(Client $client, CarbonImmutable $today): ?Invoice`

- No cycle → null.
- The client already has an unsent cycle Draft → return it.
- Compute the period containing `$today`; none (before `starts_on`) → null. If its end ≤
  `billing_opened_through`, it was already covered → null.
- Otherwise open it: start = `billing_opened_through + 1 day` when set, else the period start;
  end = period end. No day is ever left uncovered, whether the cycle changed or the job was
  down; a Draft after downtime simply spans the missed days. Create through layer 1's
  `CreateInvoice` with: service period = those dates, issue date = end, due = end + terms, the
  client's billing contact and CC list, `closes_at` = 23:59 on end in owner's timezone, and
  `sprint_id` = a sprint on the workspace's boards whose `starts_at`/`ends_at` equal exactly
  those dates, preferring the workspace board over team boards; none → null (no sprint is
  created). Set `billing_opened_through` = end. All in one transaction locking the client row.
- Opening does not require a billing contact. The Draft exists so time has a home; Close handles
  a missing contact.

The job calls this for every client with a cycle. Layer 4's `log-time` calls it too.

### Sending early (Karl's rule)

Sending a cycle Draft by hand before its `closes_at`, or deleting it, restarts the schedule:

- The sent invoice's `service_period_end` becomes the send date; its `closes_at` is cleared.
- Weekly/biweekly: `billing_cycle_starts_on` = the next day. NotaryDash sent on a Wednesday →
  the next period is Thursday plus two weeks, and the rhythm becomes Thursday–Wednesday until
  the start date is edited.
- Monthly: the next Draft runs from the next day to the end of the *following* calendar month,
  so the 1st-of-the-month rhythm holds.
- `billing_opened_through` = the send date, and the next Draft is opened immediately (not at
  the next job run), so time logged later the same day lands on it.
- Deleting follows the same rule, with the delete date in place of the send date.

### 2. Reminder — on the period's last day, from 3:00 PM owner time

For each unsent cycle Draft with `closes_at` today, `reminded_at` null and at least one line:
notify the workspace's invoice managers (`InvoiceClosingSoon`, mail + database, queued, links to
the invoice). Set `reminded_at`. No lines → no reminder.

### 3. Close — once `now ≥ closes_at`

For each cycle invoice with `sent_at` null, not Void, past `closes_at` (one already Paid in full
by a deposit is still sent, so the client gets the record):

- No lines and no payments → delete it. No email.
- Otherwise `SendInvoice` (layer 1; already atomic and idempotent).
- If sending fails (no contact) → leave it as a Draft, clear `closes_at` so it becomes a manual
  invoice, and notify invoice managers (`InvoiceCouldNotBeSent`). It is never retried silently.
  The next period opens as normal.

### 4. Overdue — daily, after midnight owner time

Invoices with status Sent whose `due_date` is before today become Overdue. Partial stays Partial
(as layer 1 status rules already say). Payment rules are unchanged: paying in full → Paid.

## Module, permission

- `OrganizationCapability::BillingCycles` under `OrganizationModule::Billing`, on by default.
- Everything is gated by the existing `invoice:manage` permission.

## Screens

- **Client edit → Billing** (already has rate, contact, billed through): add Billing cycle
  (None / Weekly / Every two weeks / Calendar month), Period starts on (date, shown for weekly
  and biweekly), CC, Payment terms (days). Cycle fields hidden when "billed through" is set. A
  line under the cycle shows the current period ("Current period: Nov 14 – Nov 27").
- **Client page → Invoices** (layer 1 follow-up): the open cycle Draft first, marked "Current
  period".
- **Invoice page**: a cycle Draft shows "Sends automatically Nov 27 at 11:59 PM" near the
  status; it disappears once sent or once `closes_at` is cleared.
- **Invoices index**: no change beyond the Overdue status showing up.

## Changing settings mid-period *(my calls)*

- Cycle turned off → the open cycle Draft's `closes_at` is cleared; it becomes a manual invoice.
- Cycle, start date or terms changed → the open Draft keeps its dates and `closes_at`; the new
  schedule applies from the next period (Karl agreed).
- Duplicate (layer 1) never copies `closes_at` or `reminded_at`. Neither is editable in the UI.

## Left to the builder

- Command and action names beyond those given; notification copy.
- How the job finds owner timezones efficiently.
- Factories and states for cycle invoices.
