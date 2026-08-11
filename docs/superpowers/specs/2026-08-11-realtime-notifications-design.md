# Real-Time Notifications

**Date:** 2026-08-11
**Status:** Designed, not built

An in-app notification feed at the bottom of the sidebar, delivered over
WebSockets, grouped by organization, that suppresses the email when the recipient
is already connected.

## Problem

Quill has one notification today — a team invitation — and it only sends email.
There is no in-app surface, so anything that happens while you are looking at the
app is invisible until you check your inbox. And once the Virtual PM starts
creating issues from client conversations, email-only becomes actively wrong: the
whole point is that activity arrives while you are working.

## Scope, honestly stated

**This is infrastructure ahead of its content.** The events worth watching arrive
live — a client submitted a request, the Virtual PM groomed an issue — belong to
Stages 2 and 3 and do not exist yet. The three producers available today
(invitation, role change, removal) are rare enough that polling would be
indistinguishable to a user.

That trade was made deliberately: the shell is the thing being built, and when the
Virtual PM starts producing activity it plugs into a pipe that already works.
Recorded here so nobody later mistakes it for an oversight.

## Transport

**Quill does not install `laravel/reverb`.** Herd already runs Reverb as a shared
local service, verified on this machine:

```
herd services:list  →  Reverb   reverb   8080   1.x   running
process             →  /Users/Shared/Herd/services/reverb/1.x/artisan reverb:start --port=8080
```

That is Herd's own Laravel app, not Quill's, so the server is external and the
package is unnecessary. Quill needs only a Pusher-protocol broadcaster pointing at
it — Reverb speaks the Pusher protocol — which means one composer dependency,
`pusher/pusher-php-server`, and `laravel-echo` + `pusher-js` on the client.

`config/broadcasting.php` gets a connection using the **`pusher` driver**, not the
`reverb` driver (that driver ships with `laravel/reverb`, which is absent):

```php
'host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false,
```

Herd's Reverb defines a single app, so Quill must use its credentials verbatim:
app id `1001`, key `laravel-herd`, secret `secret`, with `allowed_origins: ['*']`.

**This is a shared development service.** Every Herd site using Reverb connects as
the same app, which is fine locally and unacceptable in production. Production
needs its own Reverb instance or a hosted Pusher/Ably equivalent, with its own
credentials — a deploy concern, not a code one, but it must not be discovered at
deploy time.

Two channel kinds, each with a different job:

| Channel | Purpose |
| --- | --- |
| `private-App.Models.User.{id}` | Every notification for one user, across all organizations. Laravel's default channel for broadcast notifications. |
| `presence-organizations.{id}` | Who is currently connected within an organization. Joined for the user's current organization on page load. |

Authorization lives in `routes/channels.php`. The user channel compares identity.
The presence channel calls `belongsToOrganization`, which a `Client`-role contact
passes for their own organization — correct, because they are a member.

Presence exists for two reasons: it answers the email-suppression question below,
and showing who is around is wanted later. Only the first is built now.

## Storage, and the one real trap

Notifications persist via Laravel's `database` channel.

**The published `notifications` migration will not work unmodified.** The
framework stub declares:

```php
$table->morphs('notifiable');    // bigint
```

Quill's users are `char(36)` UUIDs, so this must become:

```php
$table->uuidMorphs('notifiable');
```

Left alone it fails on MySQL with errno 150 — and, per
`.ai/rules/migrations.md`, would pass a fully green SQLite suite first. The
migration is verified against the real MySQL schema before it is trusted.

Each notification's `data` carries `organization_id` and `organization_name`, so
grouping the feed needs no joins and no relation loading.

## Producers

Three, all triggerable today:

| Notification | Fired from | Data |
| --- | --- | --- |
| `Teams\TeamInvitation` (already exists) | invitation creation | team, inviter, role, organization |
| `TeamRoleChanged` | `TeamMemberController@update` | team, old role, new role, organization |
| `RemovedFromTeam` | `TeamMemberController@destroy` | team, organization |

The existing `Teams\TeamInvitation` already implements `toArray()`; it gains
`database` and `broadcast` in `via()` and is otherwise unchanged.

**Client contacts never receive internal-membership notifications.** This is
enforced at the producer, not the channel. A notification addressed to a user
cannot leak across tenants by construction — but its *content* can be
inappropriate for a client contact, and no channel check would catch that.

## Email suppression

A `PresenceLookup` service answers exactly one question: is this user currently
connected? A notification includes `mail` in `via()` only when the answer is no.

```php
interface PresenceLookup
{
    public function isOnline(User $user): bool;
}
```

Two implementations: one wrapping Reverb's Pusher-compatible channel-occupancy
API, and a fake for tests. The endpoint is verified against the running Herd
service:

```
GET http://127.0.0.1:8080/apps/1001/channels   →  200  {"channels":[]}
```

The real implementation signs its requests through `pusher/pusher-php-server`
rather than calling the URL bare — this Reverb build answered an unsigned request,
but depending on that would be relying on a detail of its configuration.

**It fails open.** Any error, timeout, or unreachable Reverb means the email is
sent. A WebSocket outage should cost a redundant email, never a swallowed
invitation.

This was chosen over the more robust alternative — delay the email and cancel it
if `read_at` is set — with the tradeoff understood: presence answers *connected*,
not *noticed*, so a backgrounded tab suppresses an email the user never sees. The
delayed-cancel approach remains available later without changing anything else in
this design.

## Interface

A bell row in `SidebarFooter`, above the user button — the space the starter
kit's Repository and Documentation links vacated.

```
SIDEBAR (expanded)              POPOVER (on click)
┌───────────────────────┐      ┌──────────────────────────┐
│  NotaryDash        ∨  │      │ Notifications   Mark all │
│  Acme Title Co     ∨  │      ├──────────────────────────┤
│                       │      │ NotaryDash               │
│  ■ Dashboard          │      │  ● Jen invited you to    │
│                       │      │    Development  2m       │
├───────────────────────┤      │ 92 Labs                  │
│  ᯤ Notifications  (3) │      │  ○ Role changed to       │
│  KM Karl Murray    ∨  │      │    Admin        1h       │
└───────────────────────┘      └──────────────────────────┘
```

- Unread count as a badge; collapses to a bare icon with the sidebar, matching
  `TeamSwitcher`.
- Grouped by organization, mirroring how the team switcher now groups by client.
- Echo pushes arrivals into the list and increments the badge without a reload.
- Actions: mark one read (on click-through), mark all read.

The **fifteen most recent** notifications and the total unread count are shared
into Inertia's page props the way `currentOrganization` and `teams` already are,
so the first render needs no request of its own. Fifteen because the popover is
scrollable but not paginated, and there is no history page to fall back to; if
that proves too few, the answer is the history page listed as out of scope, not a
larger number here.

## Flow

```
membership change
  → Notification::send($user, new TeamRoleChanged(...))
      → via(): database  → notifications row
                broadcast → private-App.Models.User.{id} → Echo → sidebar
                mail      → only if PresenceLookup says offline
```

## Failure modes

- **Reverb unreachable when sending.** `PresenceLookup` returns false, the email
  goes out, the database row is still written. The feed catches up on next load.
- **Reverb unreachable in the browser.** Echo retries; the feed is stale but not
  broken, because the initial list comes from page props rather than the socket.
- **Notification for an organization the user has left.** The row persists but the
  producer no longer fires. Reading remains possible; nothing 500s.
- **Presence channel joined for an organization the user does not belong to.**
  Rejected by channel authorization, with a test covering it.

## Testing

Reverb is never started for tests.

- `Notification::fake()` — each producer sends the right notification to the right
  user, and never to a client contact.
- `Event::fake()` — the broadcast event fires on the expected private channel.
- `PresenceLookup` fake — online suppresses mail, offline sends it, and a throwing
  implementation still sends it.
- Channel authorization — the owner passes, a non-member is rejected, a client
  contact passes for their own organization.
- Feature tests for mark-read and mark-all-read, including that a user cannot mark
  another user's notification read.
- The migration is run against MySQL by hand, per the migrations rule.

## Deliverables

Each is one small, independently mergeable PR:

1. Install `pusher/pusher-php-server`, `laravel-echo`, `pusher-js`; publish
   `config/broadcasting.php` with the `pusher` connection pointed at Herd's
   Reverb; add `.env` / `.env.example` entries. **Requires approval to add
   dependencies** — given. No `laravel/reverb`, and nothing touches the dev
   script: Herd already runs the server.
2. The `notifications` migration with `uuidMorphs`, verified against MySQL.
3. `routes/channels.php` — user and presence channel authorization, with tests.
4. `PresenceLookup`, its Reverb implementation, and its fake.
5. The three producers, with `via()` consulting `PresenceLookup`.
6. Shared page props, the bell row, the grouped popover, and Echo wiring.
7. Mark-read and mark-all-read endpoints and policies.

## Explicitly out of scope

Notification preferences and opt-outs, digest or batched email, browser push, a
routed notification history page with pagination, and displaying presence
(avatars of who is online). `DashboardController`'s pending-invitations query is
left alone — it is an actionable list, a different affordance from a feed.
