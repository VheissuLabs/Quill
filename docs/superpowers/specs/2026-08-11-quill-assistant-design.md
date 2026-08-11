# The Quill Assistant

**Date:** 2026-08-11
**Status:** Designed, not built

A chat inside Quill that answers questions about the organization you are in, and
creates clients, teams, and contacts when you ask it to.

## One agent, not two

An earlier version of this design split an internal "assistant" from a
client-facing "Virtual PM". That was wrong. It is **one agent**: one conversation
surface, one tool registry, one prompt. What changes is the asker's role, which
decides which tools they are granted.

Today only staff use it. When client contacts get access later, they meet the same
agent with a narrower grant — not a second product.

## Scope: deliberately narrow

**In:** a chat page; answering questions about the current organization's clients,
teams, and members; creating a client, a team, or a contact.

**Out:** projects, issues, tasks — those models do not exist. Nothing in this
design anticipates them beyond leaving room for another tool later. Also out:
client-contact access to the chat, editing or deleting anything, and any
notification of what the assistant did.

This narrowness is the point. The product is going to be sold, and the way to
find out whether the agent is any good is to ship a small piece and use it.

## Provider

`laravel/ai`, pointed at LM Studio on `127.0.0.1:1234` (OpenAI-compatible),
default model **`google/gemma-4-e4b`**, model and endpoint both config values.

`prism-ml/bonsai-27b` is also loaded locally and is the fallback if tool-calling
proves unreliable. That it is a config change, not a code change, is the reason
the provider sits behind `laravel/ai` rather than a hand-rolled HTTP client.

**A 4B model calls tools less reliably than a large one.** The likely failure is
answering from imagination instead of calling a tool. Two mitigations, both cheap:
the system prompt forbids answering questions about Quill's data without a tool
result, and the assistant is expected to say it does not know rather than guess.
A test covers the refusal.

## The security property

**No tool takes an organization argument.** Every tool derives the organization
from `$user->currentOrganization` on the server. The model can ask to list clients;
it cannot ask to list *NotaryDash's* clients.

This makes cross-tenant leakage impossible by construction rather than contingent
on the model behaving. A prompt-injected or simply confused model still cannot
reach another tenant's data, because there is no argument through which to try.

**Write tools run through the existing policies.** `ClientPolicy` already says only
Owner and Admin may create a client; a Member who asks the assistant to make one
is refused by the same gate that refuses them in a controller. The assistant is
not a privilege escalation path.

## Tools

| Tool | Kind | Notes |
| --- | --- | --- |
| `describe_organization` | read | Name, the asker's role, counts of clients/teams/members |
| `list_clients` | read | Each client and whether the organization or a team holds it |
| `list_teams` | read | Each team and its parent |
| `list_contacts` | read | Members with roles; `Client`-role ones marked as contacts |
| `create_client` | write | Requires `CreateClient`. Held by the organization unless a team is named |
| `create_team` | write | Requires team-create rights. Parent is the organization or a named client |
| `create_contact` | write | Requires `AddMember`. Creates or attaches a user as a `Client`-role member of one client |

Read tools exist to serve the write tools as much as the user: resolving "Acme
Title" to a client means being able to list them.

## When it asks before acting

The model decides, and the prompt sets the rule: **act when the request is
explicit, ask when a required detail is missing.**

*"Create a contact for Acme Title for Lucy"* names the client and the person, so it
acts. *"Add a contact"* names neither, so it asks. There is no draft-and-confirm
machinery and no dual-mode tool contract — tools write, and the prompt governs
whether to ask first.

**Duplicates are guarded in the tool, not the prompt.** A small model will repeat
itself. `create_client` given a name that already exists in the organization
returns the existing record and says so, rather than silently creating
`acme-title-co-1`. `create_contact` finds an existing user by email and attaches
them rather than making a second account.

## Contacts are users

A contact is a `User` holding a `Client`-role `OrganizationMembership` — the same
concept as any other member, not a parallel one. Two consequences:

- **`organization_members` gains a nullable `client_id`**, recording which client a
  contact represents. It is required when the role is `Client` and must be null
  otherwise, enforced by an observer.
- **A created contact cannot log in yet.** `users.password` is `NOT NULL`, and the
  password column is deliberately left alone: making it nullable would put
  `Hash::check()` against null into Fortify's login path, and authentication is
  not worth touching to save a migration. A new contact gets a random unguessable
  password and a null `email_verified_at` — that pairing *is* the pending state,
  and they onboard through password reset.

No invitation email is sent. Creating the record and granting access are separate
acts, and only the first is in scope.

## Conversation

`Conversation` and `Message`, persisted per message. A reload does not lose the
thread, an interrupted stream does not lose what was said, and there is a record
of what the model was told — which matters once it is writing to the database.

Streaming goes over an SSE endpoint consumed by `useHttp`, not an Inertia visit:
Inertia visits replace page props, which is the wrong shape for token-by-token
output.

## Build order

Each step is independently usable in a browser. That ordering is deliberate: the
two real bugs in the notifications work — a missing CSRF token and an SSR crash —
were both invisible to a green test suite and surfaced by running the app.

1. **Talk to it.** Chat page, `laravel/ai` against LM Studio, streamed reply, no
   tools. You can have a conversation.
2. **Ground it.** The four read tools. Ask what clients you have and get an answer
   from the database rather than the model's imagination.
3. **`create_client`.**
4. **`create_team`.**
5. **`create_contact`**, with the `client_id` migration and its observer rule.

## Failure modes

- **LM Studio not running.** The chat reports that the assistant is unreachable and
  the transcript survives. It must not 500.
- **The model answers without calling a tool.** The prompt forbids it and a test
  asserts a refusal, but a small model will sometimes do it anyway. Treated as a
  prompt-tuning problem with a model swap as the escape hatch.
- **The model calls a tool it lacks rights for.** The policy refuses, and the
  refusal is returned as a tool result so the assistant can explain rather than
  crash.
- **The model invents a client name.** `create_client`'s duplicate guard catches
  repeats; a genuinely wrong name is a soft-deletable record, which is why nothing
  here is destructive.

## Testing

Against `laravel/ai`'s fake driver — LM Studio is never contacted in tests.

- Each read tool returns only the current organization's data, and a second
  organization's records never appear.
- Asking for another organization by name still returns only your own data.
- A Member is refused `create_client`; an Owner is not.
- `create_client` with an existing name returns that record and creates nothing.
- `create_contact` with an existing email attaches rather than duplicating.
- A `Client`-role user cannot reach the chat route at all.
- An unreachable provider surfaces an error and preserves the transcript.
