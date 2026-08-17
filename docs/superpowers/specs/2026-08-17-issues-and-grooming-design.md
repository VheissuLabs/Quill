# Issues and the Grooming PM

**Date:** 2026-08-17
**Status:** Designed, not built

Supersedes **Stage 3** of `2026-08-11-virtual-project-manager-design.md`, which
said it would get its own design pass before being planned. This is that pass.

A client contact describes what they want. The assistant interrogates them until
the request is real, drafts an issue, and files it against their client's project
once they confirm it. Staff get issues too — a list on the project page, and the
same `create_issue` tool.

## What changed since the Virtual PM spec

Three of its assumptions no longer hold:

- **Roles are gone from code.** "Tools granted by the asker's role" is now
  granted by permissions, and the permission catalogue is rows.
- **`clients.default_project_id` is nullable.** Requiring a project when a client
  is created is circular, because a project may be owned by that client.
- **Client contacts are blocked from the assistant** by `DenyClientContacts`,
  which this slice removes.

One of its proposals is also dropped: `GroomingConversation` and
`GroomingMessage`. `laravel/ai` already persists a conversation per user and a
row per message, and `HasAssistantConversation` already resumes it. A second
transcript would mean two chat histories and two ways to lose one.

## One agent, two grants

The assistant spec settled this: one conversation surface, one tool registry, one
prompt, and what changes is which tools the asker is granted.
`DenyClientContacts` comes off the assistant route, and `AssistantToolbox` grants:

- **A contact** — `create_issue`, and nothing else. Not `list_clients`, not
  `list_teams`, not `describe_organization`. A contact must not learn the
  organization's internal structure.
- **Staff** — their current tools, plus `create_issue` and `list_issues`.

A contact is a membership with `client_id` set, which is already how
`isClientContact()` works. The grooming rubric is the contact branch of
`QuillAssistant::instructions()` and lives in its own file, because it is the
thing that will be tuned most.

With `create_issue` as a contact's only tool, "stay on task" needs no mechanism.
A prompt told to redirect off-topic questions has nowhere else to go. It gets a
test, not code.

## Schema

```
issue_types     id, organization_id (nullable), name, position, archived_at
                unscoped rows are templates; each organization gets a copy

issues          id, organization_id, project_id, client_id, issue_type_id,
                reported_by, conversation_id (nullable),
                number, title, description, acceptance_criteria (nullable),
                closed_at (nullable), timestamps, softDeletes
                unique(project_id, number)

issue_drafts    id, conversation_id, client_id, project_id, reported_by,
                issue_type_id, title, description, acceptance_criteria,
                issue_id (nullable), discarded_at (nullable), timestamps
```

**Types are per-organization rows, and no code may name one.** Organizations will
want different classifications, and will rename and retire the ones they have, so
`bug`, `feature` and `enhancement` appear exactly once in the whole codebase — as
seed data. They are not an enum, not a config array, and not a constant.

The table follows the pattern the roles work established: unscoped rows are the
templates, and creating an organization copies them, so one owner's edits cannot
reach another's. That also means the copy action already has a sibling to mirror
(`SeedDefaultRoles`).

What "no code names a type" rules out, concretely:

- No `match` on a type name for an icon, a colour, or a filter. If display needs
  a colour, that is a column on the row.
- The assistant's `create_issue` schema builds its allowed values from *this
  organization's* rows at call time. A type added this morning is offerable this
  afternoon with no deploy.
- The grooming rubric lists the organization's own types, so classification is
  against what that organization actually uses.
- Tests seed a type and assert the issue carries the type they seeded. No test
  asserts the string `bug`.

**Retiring a type does not orphan its issues.** `issue_type_id` is
`restrictOnDelete` — a type in use cannot be deleted — and `archived_at` hides a
type from the pickers and from the assistant's schema while leaving existing
issues readable. Renaming is free, because issues reference the row.

`project_id` is not nullable — there is no organization-level orphan issue.

**`client_id` is nullable, and that is not a compromise.** A project may be owned
by a team rather than a client — the seeded "Delivery Internal Tooling" is exactly
that — and internal work is for nobody. So: null when staff file against a
team-owned project, and **required whenever the reporter is a contact**, which an
observer enforces because the schema cannot. A contact's issue without a client
would be an issue nobody can be shown.

`organization_id` is carried for the same reason every other model carries it —
scoping is a plain `where` rather than a walk up the owner chain.

`conversation_id` is nullable because staff file issues directly, without a
conversation.

## Keys and status

**A `number` per project**, unique within it, allocated inside a transaction that
locks the project row. Displayed as `#14`, addressable at
`/projects/{project}/issues/{number}`. No `ACME-` prefix: the project already
supplies the context, and a prefix forces a decision about whose slug wins when
one team's project serves several clients.

**Open and closed is `closed_at`**, not a status enum. Open is null. It adds no
vocabulary and will not collide with whatever states the scrum slice wants.

## The grooming flow

```
Contact → /assistant  (the same chat staff use)
  POST message → persisted by laravel/ai
              → streamed assistant turn
              → or create_issue → IssueDraft (pending: no issue, not discarded)
                                → draft card renders in the transcript
                                → Confirm → Issue, draft points at it
                                → "not quite" → draft discarded, conversation continues
```

`create_issue` **never writes an `Issue`.** It validates its arguments, writes an
`IssueDraft`, and returns a tool result describing the draft. The client sees
their words interpreted before anything is filed — the cheapest defence against a
confidently wrong ticket.

Confirmation is a **card in the transcript**, not a separate page: Confirm files
it, "not quite" discards the draft and hands the conversation back to the model
with the draft as context, so correcting it is just more conversation.

## Derived state, not a status machine

The Virtual PM spec proposed a conversation status enum — `Active`,
`AwaitingConfirmation`, `Completed`, `NeedsHuman`, `Abandoned`. Every one of
those is derivable and none of them is stored:

| Question | Answer |
| --- | --- |
| Awaiting confirmation? | A draft exists with no `issue_id` and no `discarded_at` |
| Completed? | That draft points at an issue |
| How many turns? | Count the contact's messages in the conversation |

The draft carries `issue_id` and `discarded_at` rather than a status string, for
the same reason issues carry `closed_at`: two timestamps say it without adding a
vocabulary that code has to agree on.

`NeedsHuman` is the one that is not state at all — **it is an event.** When the
turn cap is reached with no draft, the organization is notified through the
existing bell, an activity-log entry records it, and the contact is told a person
will pick it up. That reuses the notification producer and the audit log instead
of a status column the scrum slice would replace.

## Permissions

Three new rows in the catalogue: `issue:create`, `issue:update`, `issue:close`.

The seeded **`client` bundle gains `issue:create` only** — that is how a contact is
permitted to file, through the same machinery as everyone else, and it means an
owner can revoke it for their organization without a code change. `member`,
`admin` and `owner` gain all three, because members are the people doing the work
the issues describe.

Because the templates are unscoped rows copied per organization, existing
organizations need the new grants. Local databases only, so `migrate:fresh --seed`
is the upgrade path.

## When there is no destination

A contact's issue lands in `client.default_project_id`. That column is nullable
and stays nullable. If a contact files while it is null, `create_issue` refuses,
tells them someone will follow up, and notifies the organization — the same
escalation path as the turn cap. Inventing a project would be worse than saying
so.

## Bounds

A client-facing chat is a spend surface, so both limits ship with it:

- **A per-conversation turn cap**, counted from the contact's persisted messages.
- **Per-contact rate limiting** via `throttle` middleware on the message route.

A leaked session must not be able to run up a token bill.

## Staff surface

- **Project page** — the `project-issues-placeholder` becomes the issue list:
  number, title, type, reporter, open or closed. Closed issues hidden behind a
  toggle.
- **Issue page** — title, description, acceptance criteria, type, client,
  reporter, and a link to the conversation it came from when there is one.
- **Create and close** — a form for staff holding `issue:create`, and a close
  action for `issue:close`. Both authorized on the route, per
  `.ai/rules` conventions and the controller work in PR #3.

## Testing

Against `laravel/ai`'s fake driver; LM Studio is never contacted.

- Vague input produces no tool call and a follow-up question.
- A complete description produces a `create_issue` call carrying one of that
  organization's own type rows.
- An organization that renames or archives a type changes what the tool offers,
  with no code change.
- A type in use cannot be deleted; archiving it hides it and leaves its issues
  readable.
- An off-topic question from a contact produces a redirect and no tool call.
- `create_issue` writes a draft and no issue.
- Confirming a draft creates the issue in that client's default project.
- Discarding a draft creates nothing and leaves the conversation usable.
- A contact whose client has no default project gets a refusal, and the
  organization is notified.
- The turn cap holds, notifies the organization, and logs the escalation.
- A contact is granted `create_issue` and nothing else — asserted against the
  toolbox, not the prompt.
- A contact cannot reach the project list, another client, or another client's
  issues.
- Issue numbers are sequential per project and survive concurrent creation.
- An issue filed by a contact without a client is refused by the observer.
- Staff may file against a team-owned project with no client at all.
- A member without `issue:create` is refused; one with it is not.

## Build order

Each step is independently mergeable and leaves the app usable.

1. **`Issue`, `issue_types`, permissions.** Model, migrations, factories, the
   seeded type templates and their per-organization copy, the grants, and the
   per-project number allocation. No UI.
2. **Staff surface.** Issue list on the project page, issue page, create and
   close, all authorized on routes.
3. **`create_issue` for staff.** The tool, writing a draft, and the confirmation
   card in the chat.
4. **Contact access.** `DenyClientContacts` removed from the assistant, the
   contact-only grant in `AssistantToolbox`, and the grooming rubric.
5. **Bounds and escalation.** Turn cap, rate limiting, the escalation
   notification and its activity entry.

Implementation waits on PR #3 (standard controller actions), which touches the
assistant controller and the project routes this builds on.

## Failure modes

- **Provider down or times out.** The transcript is already persisted per
  message, so nothing the contact typed is lost and the turn replays.
- **Malformed tool arguments.** `create_issue` validates and returns a tool
  error, letting the model correct itself rather than 500ing at a client.
- **Two issues filed at once.** The number allocation locks the project row, and
  `unique(project_id, number)` is the backstop.
- **A draft confirmed twice.** Confirmation is idempotent: a draft already
  `confirmed` returns its existing issue rather than filing a second one.
- **The model drafts for the wrong client.** It cannot. `create_issue` takes no
  client argument — the client comes from the contact's membership, the same
  property that makes cross-tenant reads impossible elsewhere.

## Out of scope

Workflow beyond open and closed, estimates, sprints, assignment, comments,
attachments, labels, feature voting, and Jira or GitHub import.

Per-organization issue types are **in** scope as data — the table is scoped, the
copy happens on organization creation, and the assistant reads whatever rows an
organization has. The *screen* for editing them is not in this slice; it belongs
with the roles settings screen, which has the same shape.
