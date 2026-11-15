# Billing: invoices

Quill takes over invoicing from Atlas so that Atlas, and Filament with it, can be shut down.
Clients, projects, issues and sprints already live in Quill; billing is the last thing that
keeps Atlas alive.

This is the first of five sub-projects. It builds the invoice itself. The rest build on it:

1. **Invoices** (this design)
2. **Billing cycles** — a Draft opens per client per sprint or calendar month and is closed and
   sent at the end; recurring invoices; overdue marking; reminders.
3. **Payments** — Square checkout from the public invoice page, recorded as a payment.
4. **MCP tools** — `search-clients`, `create-task`, `get-sprint`, `log-time`,
   `create-draft-invoice`, `get-invoice`, `update-invoice` rebuilt against Quill.
5. **Atlas import** — a one-time copy of invoices, lines and payments.

Each ships as a layer of a PR stack, in that order.

## Intent

- One app for running client work and billing it.
- **Time is tracked on the invoice.** There is no time entries table. An invoice line *is* the
  record of time worked: hours × rate, with an optional link to the Issue it was for. This is
  the deliberate simplification over Atlas, where time entries and lines had to be kept in
  sync. Logging time (later, via `log-time`) means adding a line to the client's open Draft.
- Built the Quill way: Inertia + Vue pages, workspace-scoped, enums for status, policies and
  permissions like every other module.

### Goals

- Create, edit and send invoices to a client, with every money feature Atlas has: tax,
  discount (percentage or fixed), deposit (percentage or fixed), notes, terms, CC list.
- Record payments by hand (check, wire, …); status follows the money.
- A public, tokenized invoice page the client can open from the email without signing in, and
  print.
- Totals computed exactly as Atlas computes them, so imported invoices reconcile to the cent.

### Non-goals (this layer)

- Expense reports. Never used in production; not ported.
- A separate time tracker, timers, or time entries.
- Automatic billing, recurring invoices, overdue marking, reminders (layer 2).
- Card payments / Square (layer 3). MCP tools (layer 4). Data import (layer 5).
- Generated PDF files. Atlas has none either; the public page is print-friendly. Adding a PDF
  library would be a new dependency and needs approval separately.

### Anti-goals

- A port of Atlas's shapes for their own sake: no polymorphic `Status` model, no `item_type`
  for expense/time-entry/task lines, no `time_entry_id`.
- Invoices visible across workspaces. Every query is scoped to the current organization.
- Totals that drift from the lines. Totals are always recomputed from lines and payments,
  never edited directly.

## Approaches considered

1. **Billing module (chosen).** A `Billing` case on `OrganizationModule`; invoices belong to a
   Client in the workspace; billing settings are per workspace. Matches how Feedback,
   Development and Teams work, so routes, policies, permissions and settings follow existing
   patterns.
2. **Vheissu Labs only.** Global settings, no per-workspace scoping. Faster, but a special case
   in a multi-workspace app, and redone the moment a second workspace wants billing. Rejected.

Reshaping Atlas first (dropping its time entries) was also considered and rejected: Atlas is
being scrapped, so effort spent there is thrown away. Data shape differences are absorbed by
the import in layer 5.

## Domain mapping from Atlas

| Atlas | Quill |
|---|---|
| Organization (client company) | `Client` |
| Contact | `ClientContact` |
| Task | `Issue` (`Issue::reference()` gives "Project #12") |
| Organization.billing_rate | `Client.billing_rate` |
| Organization.billing_contact | `Client.billing_contact_id` → `ClientContact` |
| Organization.billed_through_id | `Client.billed_through_client_id` → `Client` |
| Project.billing_rate | `Project.billing_rate` |
| InvoiceSettings (global) | `billing_settings` row per organization |
| WorkType (global) | `WorkType` per organization |
| Status model (invoice) | `InvoiceStatus` enum |

`billed_through_client_id` exists because some clients' work is billed to another client (the
92 Labs subcontract: KMS Tools work is invoiced to 92 Labs). An invoice's `client_id` is always
the **bill-to** client. The projects and issues it may reference are those of the bill-to
client **plus every client billed through it** (one level, as Atlas's `projectsBilledTo`
does). So a 92 Labs invoice can carry KMS Tools projects and issues. `project_id` and each
line's `issue_id` are validated against that set, and the issue picker is scoped to it. In this
layer the relationship also picks the default bill-to client when starting an invoice from a
project; layer 2 uses the same set to group projects onto one cycle invoice. *(Builder-check
call: matches Atlas.)*

## Data

Money is `decimal(12,2)`, rates and percentages `decimal(5,2)`/`decimal(12,2)` as Atlas has
them, cast with `decimal:2`. Hours use `decimal(8,2)`.

### `billing_settings` (one per organization)

`organization_id` (unique), `invoice_prefix` (default `INV`), `default_rate`,
`payment_terms_days` (default 30), `tax_rate` (nullable default), `default_notes`,
`default_terms`, `from_address` (text block printed on the invoice), timestamps.

Created lazily on first access (`Organization::billingSettings()` returns the row, creating it
with defaults). Layers 2 and 3 add cadence and encrypted Square credentials here.

### Additions to existing tables

- `clients`: `billing_rate` nullable, `billing_contact_id` nullable FK `client_contacts`
  (nullOnDelete), `billed_through_client_id` nullable FK `clients` (nullOnDelete).
- `projects`: `billing_rate` nullable.

### `work_types`

`organization_id`, `name`, `color`, `is_active`, `sort_order`, timestamps. Unique
`(organization_id, name)`.

### `invoices`

- `organization_id`, `client_id` (bill-to), `project_id` nullable, `sprint_id` nullable
- `number` — unique per organization (`unique(organization_id, number)`);
  `{prefix}-{year}-{0001}` as Atlas does (`INV-2026-0012`), year of creation. Assigned at
  creation as the highest existing `{prefix}-{year}-` number in the organization + 1, inside a
  transaction that locks the organization's `billing_settings` row so concurrent creates can't
  collide. Taking max + 1 (not a stored counter) means imported Atlas numbers are respected
  automatically. Rollout rule: the layer 5 import runs before anyone creates an invoice in
  Quill. *(Builder-check call.)*
- `public_token` — unique random string, generated at creation
- `status` — `InvoiceStatus` enum
- `contact_id` nullable FK `client_contacts` (who it's addressed to), `cc_emails` json array
- `issue_date`, `due_date`, `service_period_start`, `service_period_end` (nullable dates)
- `subtotal`, `tax_rate`, `tax_amount`, `discount_type` (`AdjustmentType` enum, nullable),
  `discount_value`, `discount_amount`, `deposit_type` (`AdjustmentType`, nullable),
  `deposit_value`, `deposit_amount`, `total`, `amount_paid`
- `notes`, `terms`
- `sent_at`, `voided_at` nullable timestamps
- timestamps

Atlas stores discount as `discount_percentage` + `discount_amount` and overloads
`discount_amount` as the input for fixed discounts. Quill separates input (`*_value`, either a
percentage or a dollar figure depending on `*_type`) from result (`*_amount`). The import maps
between them.

### `invoice_lines`

`invoice_id` (cascade), `description`, `quantity` `decimal(8,2)`, `unit` (`LineUnit` enum:
`hours`, `units`), `rate`, `amount`, `work_type_id` nullable, `issue_id` nullable (nullOnDelete),
`reference_url` nullable (2048), `sort_order`, timestamps.

A line for time is `unit = hours`; a fixed-price line is `units` with quantity 1.
`amount = round(quantity × rate, 2)`.

### `invoice_payments`

`invoice_id` (cascade), `amount`, `paid_on` date, `method` (`PaymentMethod` enum: `card`,
`check`, `wire`, `ach`, `cash`, `other`), `reference` nullable, `notes` nullable, timestamps.

All four new models use Spatie activity log, like the rest of Quill.

## Totals (must match Atlas)

```
subtotal       = Σ line.amount
tax_amount     = subtotal × tax_rate / 100            (on subtotal before discount)
discount_amount= percentage ? subtotal × value / 100 : value
total          = subtotal + tax_amount − discount_amount
amount_paid    = Σ payment.amount
amount_due     = max(0, total − amount_paid)          (derived, not stored)
deposit_amount = percentage ? total × value / 100 : value
```

Round each stored figure to 2 places. `Invoice::recalculate()` does this and is called after any
line or payment change and after editing tax/discount/deposit. One place, one formula.

## Status

`InvoiceStatus`: `Draft`, `Sent`, `Partial`, `Paid`, `Overdue`, `Void`.

- Created as `Draft`. Only Drafts can have lines, rates, tax/discount/deposit changed.
  Description, reference link, work type, notes and service period stay editable after sending
  (matches the Atlas MCP rule).
- **Send** sets `sent_at` and `Sent`, emails the bill-to contact with CC.
- Payments can be recorded at any status except Void, including on a Draft (deposits are often
  paid before the work is invoiced). Payments can be edited or deleted unless the invoice is
  Void.
- After any recalculation, status is derived, matching Atlas, and **never touches Void**:
  `total > 0 and amount_paid ≥ total` → `Paid` (even from Draft); `amount_paid > 0` → `Partial`;
  otherwise `Draft` if `sent_at` is null, else `Sent` (or `Overdue`, which layer 2 sets). So
  removing the only payment from an unsent invoice returns it to Draft, and a $0 invoice is
  never auto-Paid. Lines and money fields are editable only while `sent_at` is null and the
  status isn't Paid. *(Builder-check calls: match Atlas.)*
- **Void** from any non-Draft, non-Paid status. Sets `voided_at`. Voided invoices are read-only
  and skipped by recalculation.
- Drafts can be deleted; nothing else can.
- Atlas's `cancelled` status is not ported; the import maps it to `Void`.

## Module, permission, settings

- `OrganizationModule::Billing` (`billing`) with one capability `OrganizationCapability::Invoices`
  (`invoices`). Layer 2 adds a `BillingCycles` capability.
- `OrganizationPermission::ManageInvoices` (`invoice:manage`) gates everything here, including
  billing settings, work types and the client/project rate fields. No separate view permission;
  invoices are financial and only managers see them.
- `InvoicePolicy`, scoped by organization like `ClientPolicy`.
- Billing is **on by default** like every other module (no setting row = enabled). Karl is the
  only user, so existing workspaces simply get it; no seeding of disabled rows. A migration
  grants `invoice:manage` to existing owner roles, the same way earlier permissions were added.

## Screens (Inertia + Vue, shadcn-vue)

Under `{current_organization}`:

- **Invoices index** — table: number, client, issue date, due date, total, amount due, status
  badge. Filters by status and client.
- **Invoice page** — one page, edit-in-place while Draft. Header (client, contact, CC, dates,
  service period, project, sprint), lines table (description, issue picker, reference link,
  work type, quantity, unit, rate, amount; drag to reorder), totals panel with tax/discount/
  deposit inputs, notes and terms, payments list with "Record payment", actions: Send, Void,
  Duplicate, Delete (Draft), Copy public link.
- **New invoice** — choose client (and optionally project); prefill contact from
  `billing_contact_id`, rate from project → client → settings default, terms/notes/tax from
  settings, due date from payment terms.
- **Billing settings** — the `billing_settings` fields plus a work types list.
- **Client edit** — billing rate, billing contact, billed through. **Project edit** — billing
  rate.

Issue picker on a line reuses the existing issue lookup (`IssueLookupController`), limited to
the invoice's client's projects. Picking an issue fills the description from the issue title
if empty.

## Public invoice page and email

- `GET /invoice/{token}` — no auth, outside the organization prefix. Renders the invoice with
  the organization's name, logo and `from_address`. Print stylesheet. Draft and Void tokens
  return 404 / a "void" notice respectively. Layer 3 adds the Pay button here.
- Sending uses a `InvoiceSent` notification/mailable to the contact, CC list on the message,
  with the public link. Queued, following the existing notifications.

## Also included

- **Duplicate** copies header and lines into a new Draft with today's dates (Atlas has this
  and layer 2's recurring invoices reuse it).

## Left to the builder

- Exact controller split (resource controllers following `Clients/*` is expected).
- Component breakdown for the invoice page.
- Whether `recalculate()` is triggered by model events or explicit calls, as long as there is
  one formula and it can't be bypassed by bulk updates.
- Factories and seeders for the new models.
