# The Virtual Project Manager

**Date:** 2026-08-11
**Status:** Designed. Being built in three stages — see Staging.

The first slice of Quill. Quill will eventually combine a help desk, scrum
management, and feature voting; this document covers only the piece that makes
the rest worth having, plus the minimum tenancy it needs to stand on.

## Problem

Work for three companies currently lives in three tools — Jira, GitHub Issues,
and one person's memory. Consolidating them is the long-term goal, but
consolidation alone is a filing exercise. The thing worth building first is the
part no existing tool does: **an AI project manager that a client talks to
directly, which refuses to let a vague request through.**

Today a client sends a sentence. Somebody then has to ask the five follow-up
questions that turn it into work. That labour is the cost, and it falls on the
wrong person.

## What this builds

A full-screen chat a client contact opens. They describe what they want. The
Virtual PM interrogates them — classifying the request as a bug, feature, or
enhancement, and pressing for the details a real ticket needs — and when it
judges the picture complete, it creates the issue.

The client does the grooming. Nobody on the delivery side writes the ticket.

## Staging

The design above is the destination. It is built in three stages, each with its
own plan, because the risk is concentrated in one place and it is cheaper to
prove the plumbing before betting on the judgement.

**Stage 1 — Tenancy and setup.** Organizations, Teams, Clients, Contacts, and
Projects, with the owner-side screens to create them. No AI at all. This is the
substrate everything else needs, and it is ordinary Laravel work that can be
tested exhaustively.

**Stage 2 — An internal, read-only Virtual PM.** A chat that answers questions
about the organization's own contacts, clients, and projects: *who are the
contacts at Acme Title Co? which clients does 92 Labs have?* Staff only —
owners and admins, never client contacts. Read-only tools scoped to the asker's
organization.

This stage exists to prove the entire AI path — the `laravel/ai` driver against
LM Studio, SSE streaming into the chat UI, tool calling and result handling,
and testing against the fake driver — while the tools are read-only and the
audience is internal. A scoping mistake here is invisible to clients, and a bad
prompt costs nothing.

**Stage 3 — The client-facing grooming PM.** The `create_issue` tool, the
grooming rubric, client confirmation, and `Issue` itself. Everything below
about grooming, single-tool design, and turn caps belongs to this stage; it is
documented here because the reasoning shaped the earlier stages, not because it
is being built yet.

## Scope decomposition

Quill is five independent subsystems. Each gets its own spec, plan, and build:

1. **The Virtual Project Manager** — this document.
2. Help desk.
3. Scrum / sprint management.
4. Feature voting.
5. Multi-email identity (one human, several verified addresses, several orgs).
6. Billing, per agent seat.
7. Platform admin — cross-organization account troubleshooting and billing
   adjustment.

Nothing in this slice blocks the others. Multi-email in particular is only
*enabled* here: no part of this design assumes one email per user.

## Tenancy

The Virtual PM creates an issue for a client, in a project, inside an
organization. That hierarchy did not exist yet, so it is settled here.

```
Organization  (NotaryDash, 92 Labs, VheissuLabs)
├── Teams          departments — existing Team model, + organization_id
├── Clients        companies — "Acme Title Co"
│   ├── contacts   Users holding a Client-role org membership
│   └── default_project_id   (required)
├── Projects       + Issues
└── members        Users, via OrganizationMembership
```

### Team is reparented, not replaced

`Team` gains an `organization_id` and otherwise keeps its slug generation,
`Membership` pivot, invitations, and policies. Departments are what teams
already are; nothing existing is discarded.

### Membership splits in two

`OrganizationMembership` (Owner / Admin / Member / Client) governs
organization access. The existing team-level `Membership` continues to govern
departments.

A client contact holds an organization membership with the `Client` role and
**no team membership at all**. That is what keeps clients out of internal
departments by construction, rather than by policy checks repeated at every
call site.

It is also the billing boundary. Quill will charge per agent seat, and client
contacts are free — deliberately, so that a freelancer taking on a client is
never billed for growth they have not been paid for yet. Because "billable" and
"holds a non-Client organization role" are the same set, the billing slice can
count seats with a query rather than a migration. Nothing about billing is built
here; the schema just needs to not preclude it, and it does not.

Seat count is the *default*, not the invoice. Some organizations will be comped
outright — people who helped build the thing, or clients being worked with
directly — so billing resolves to an override when one exists and a seat count
when one does not. That override belongs to the billing slice, but it means
nothing downstream should treat "number of non-Client members" as the amount owed.

### Projects are polymorphic

A project is owned by either a `Client` (client-wide) or a `Team` (a
department's project):

```php
// Project
organization_id            // direct
owner_type / owner_id      // morphTo: Client | Team

/** @return MorphTo<Model, $this> */
public function owner(): MorphTo
```

`organization_id` is deliberately redundant — both owners already belong to an
organization — because deriving it would force a polymorphic join on every
scoped query. That owner and project share an organization is enforced in a
model observer, not by a database constraint.

Per `.ai/rules/models.md`, `morphTo()` is the one relation that still carries a
`@return` docblock; no other relation in this design gets one.

### Clients never choose a destination

A client contact has exactly one route: their organization's Virtual PM chat.
`Client.default_project_id` decides where the issue lands. The client never
picks a project and never sees the list — internal department structure is not
their concern.

`issues.project_id` is **not nullable**. There is no such thing as an
organization-level orphan issue. Because the destination comes from the client,
`Client.default_project_id` is required too.

### Setup is manual and owner-driven

An organization owner creates the project, creates the client pointing at it,
and invites the contact. Nothing is auto-provisioned.

This is a deliberate choice over auto-creating a project per client: the owner
knows whether a given client's work belongs in its own project or in a
department's, and guessing wrong creates a project nobody wanted.

The consequence is that this slice must include that setup, or the Virtual PM
has no clients to talk to. It is plain CRUD following the existing team
screens — see Deliverables.

### What the platform admin implies here

A platform admin — one person, above every organization, troubleshooting accounts
and adjusting billing — is subsystem 7 and is not built in this slice. But it
constrains one decision that gets made now, in PR 1's policies:

**Tenant isolation is enforced in policies and explicit queries, never by a global
Eloquent scope.** A global scope on `Organization` would be the obvious way to keep
one org's data away from another's, and it is the wrong one: the admin section has
to read across every organization, and it would spend its life calling
`withoutGlobalScopes()`. Code that fights its own guardrail stops being a guardrail.

Two other consequences, recorded so the admin slice does not have to relitigate them:

- **Platform admin is not an organization role.** `OrganizationRole::Owner` means
  owner *of one organization*, not of Quill. The platform admin is a separate
  attribute on `User`. Nothing in this slice needs it, and nothing in this slice
  precludes adding it.
- **Troubleshooting an account implies impersonation**, which needs its own audit
  trail and its own careful design. It is named here so it arrives as a designed
  feature rather than an afterthought bolted onto login.

### User orientation

`User.current_team_id` becomes `current_organization_id`. One human belongs to
several organizations; organization is the real switch, and department
selection happens inside it.

## The Virtual PM

### One tool

The PM is given exactly one tool: `create_issue`. It converses, and when it
judges the requirement complete it calls that tool with the structured issue.

This collapses two problems into one. There is no separate "is it done yet?"
call that can disagree with the conversation, and although the judgement itself
is the model's, *whether it judged* is a fact a test can assert: either the tool
was called or it was not.

The alternative considered was a per-type required-field schema — bugs need
steps/expected/actual, features need problem/outcome/acceptance-criteria — with
the conversation driven until every field was filled. That is more testable and
was rejected deliberately: a field checklist produces filled-in-but-useless
answers, and the product's value is a PM that knows the difference. The cost of
choosing judgement is that "it ended too early" becomes prompt tuning rather
than a failing assertion. Two things bound that cost: the rubric lives in one
editable file, and a conversation that never converges escalates to a human
(below).

### Components

```
GroomingConversation   belongs to Client + contact (User); status; has many messages
GroomingMessage        role, content — the persisted transcript
VirtualProjectManager  transcript → next assistant turn, or a create_issue call
CreateIssueFromConversation   writes the Issue into the client's default project,
                              linked back to the conversation
```

`GroomingConversation.status` is an enum: `Active`, `AwaitingConfirmation`,
`Completed`, `NeedsHuman`, `Abandoned`.

A `create_issue` call does **not** itself persist an issue. It validates the
arguments, stores the draft, and moves the conversation to
`AwaitingConfirmation`. `CreateIssueFromConversation` runs only after the client
confirms, and moves the conversation to `Completed`.

The transcript is persisted per message, not assembled at the end. It is the
audit trail for how an issue came to be worded the way it is, and it means a
provider failure never loses what the client typed.

`Issue` carries only what the Virtual PM produces: type (Bug / Feature /
Enhancement), title, description, acceptance criteria, project, client,
reporting contact, and the originating conversation. No status workflow, no
estimates, no sprint fields — those belong to the scrum slice.

### The rubric lives in one file

The prompt is the thing that will be tuned most, so it is a single file rather
than strings scattered through the service. It carries three jobs:

1. Classify the request as bug, feature, or enhancement.
2. Interrogate until the requirement is real — pressing on vague answers, not
   merely collecting them.
3. **Stay on task.** Off-topic questions get declined and redirected, the way a
   PM redirects a meeting.

The third needs no mechanism. With `create_issue` as the only tool, a PM
instructed to stay on task has nowhere else to go. It gets a named test, not
code.

### Client confirmation

When the PM calls `create_issue`, the drafted issue is shown to the client and
persisted only once they confirm. It is their words being interpreted, and this
is the cheapest defence against a confidently-wrong ticket.

## Model and provider

The AI provider is **not** decided here, and does not need to be. `laravel/ai`
(v0.10.x, namespace `Laravel\Ai\`) abstracts the driver, so the model is
configuration. Development runs against a local model in LM Studio over its
OpenAI-compatible endpoint; a hosted model can be swapped in per environment
without touching the conversation code.

This is why no model or per-token cost appears in this design. Installing
`laravel/ai` requires approval before the plan is executed.

## Flow

```
Client contact → /pm  (Inertia page, full-screen chat)
  POST message → persist GroomingMessage
              → VirtualProjectManager → streamed assistant turn
              → or create_issue call → draft shown → confirmed → Issue
```

Streaming goes over a plain SSE endpoint consumed by `useHttp`, not an Inertia
visit. Inertia visits replace page props, which is the wrong shape for
token-by-token output. Routes are generated by Wayfinder.

## Bounds

A client-facing chat is a spend surface, so two limits ship with the first
version rather than after the first surprise:

- A per-conversation turn cap.
- Per-contact rate limiting.

A leaked session must not be able to run up a token bill.

## Failure modes

- **Provider down or times out.** The transcript is already persisted, so the
  conversation survives. The client sees a retryable error and the turn
  replays. Nothing they typed is lost.
- **Malformed tool arguments.** The `create_issue` handler validates and
  returns a tool error, letting the PM correct itself rather than returning a
  500 to the client.
- **Turn cap reached with no tool call.** The conversation is flagged for a
  human and the organization is notified. This is the honest answer to
  judgement that never converges: the request is never silently lost, it lands
  on someone's desk instead of in the backlog.

## Testing

`laravel/ai`'s fake driver makes these assertions rather than impressions:

- Vague input produces no tool call and a follow-up question.
- A complete description produces a `create_issue` call with the right type.
- An off-topic question produces a redirect and no tool call.
- A confirmed draft creates an `Issue` in that client's default project.
- An unconfirmed draft creates nothing.
- The turn cap holds and flags the conversation for a human.
- A client contact cannot reach any department, project list, or other client.

Feature tests throughout, per the project's test rules; models use factories.

## Deliverables

Each numbered item is one small, independently mergeable, independently tested
pull request. No step depends on a later step being designed differently, and
none of them is a "massive PR" — that is a working constraint, not an aspiration.

### Stage 1 — Tenancy and setup

1. `Organization` and `OrganizationMembership`: models, migrations, factories,
   the role enum, policies. No UI.
2. Reparent `Team` under `Organization` (`organization_id`), and
   `User.current_team_id` → `current_organization_id` with the switcher.
3. Organization screens — create and settings, mirroring the existing team
   screens.
4. `Project`: model with the polymorphic `Client | Team` owner, the
   same-organization observer, and its screens.
5. `Client`: model with its required `default_project_id`, and its screens.
6. Contact invitation — inviting a user as a `Client`-role organization member
   attached to a client.

### Stage 2 — Internal read-only Virtual PM

7. Install `laravel/ai`, configure the LM Studio driver, and prove one call
   end to end against the fake driver. **Requires approval to add the
   dependency.**
8. `Conversation` and `Message` — the persisted transcript, no AI wired in.
9. Non-streaming chat: ask a question, get an answer, no tools.
10. Read-only tools — list clients, contacts, projects — scoped to the asker's
    organization, staff-only.
11. SSE streaming into the chat UI.
12. Rate limiting and a turn cap.

### Stage 3 — Client-facing grooming PM

13. `Issue`, the grooming rubric, `create_issue`, client confirmation,
    `CreateIssueFromConversation`, and the client-facing route with its
    policies.

Stage 3 gets its own design pass before it is planned; the destination described
above is the current intent, not a commitment made in advance of stages 1 and 2
teaching us something.

## Explicitly out of scope

Help desk, feature voting, sprints and estimates, multi-email identity,
Jira/GitHub import or sync, unauthenticated or magic-link client access, and any
workflow state on `Issue` — issues are created and then sit there until the
scrum slice gives them somewhere to go.
