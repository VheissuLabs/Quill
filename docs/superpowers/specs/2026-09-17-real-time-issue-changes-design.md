# Real-time issue changes

When someone changes an issue, everyone else looking at it sees the change without
reloading. This covers the issues index, the issue detail page, and the sprint board.

This is the first of three sub-projects. Comments and typing indicators, and the public
portal, are separate designs that build on the conventions this one establishes.

## What the user sees

A change made by anyone in the organization appears on other people's screens within about
a second. Status, assignee, team, sprint, points, priority, title, ordering, creation and
deletion all count.

Most of that comes free from the model's lifecycle, but not all of it: bulk updates bypass
Eloquent's events entirely, and this codebase uses them. Those paths are listed below and
dispatch explicitly. Assuming the observer covers everything would have left sprint
completion — dozens of cards moving at once — completely silent.

Updates apply immediately and unconditionally, including while the viewer is dragging a row
or has a menu open. This was chosen deliberately over queuing updates until the viewer is
idle. If it proves hostile in practice, `useRowDrag` already exposes a signal for "a drag is
in progress" and the composable can hold updates behind it without touching anything else.

The person who made the change does not receive their own event.

## The event

`App\Events\IssuesChanged`, implementing `ShouldBroadcastNow` and `ShouldDispatchAfterCommit`.

It carries an organization id and a list of issue ids:

```
{ "issues": [14, 15, 16] }
```

It broadcasts as `issues.changed` on the private channel `organizations.{id}`.

The payload is deliberately thin. It says *that* something changed, never *what*. Three
consequences follow:

- The channel never carries anything sensitive, so channel membership does not have to
  answer per-project visibility questions.
- There is no serialized shape to keep in step with `IssueResource`.
- The contract is one another client can depend on without our being able to break it by
  adding a field to a resource.

The channel name and the event name are the public surface of this feature. They are defined
as constants on the event rather than as literals spread through the codebase, because a
second client will need to read them.

## Where it fires

The ordinary path is `IssueObserver` on `saved` and `deleted`. `saved` covers creation, so a
new issue appears in other people's lists on its own. That handles every single-issue change:
the chip menus, the slash commands, the detail page, a tinker session, a future MCP tool call.

Reading the organization id requires the issue's project. Lazy loading is prevented outside
production, so the observer uses `loadMissing('project')` rather than touching `$issue->project`
and hoping it was eager loaded.

Three paths change many issues at once and need more than the observer.

**`IssueOrderController` becomes a single query.** It currently loads every issue in the list
and calls `update()` on each one, so a drag on a 512-issue backlog writes 512 rows
individually — and would dispatch 512 events. It changes to one bulk write of the positions,
dispatching once with the ids it was given. This is worth doing on its own merits; the
broadcast just makes it urgent.

**`Sprint::complete()` and `Team::moveIssuesTo()`** already use bulk updates, so no model
event fires. Each plucks the ids before updating and dispatches once.

`Team::returnSprintWorkToBacklog()` needs nothing: it deliberately loops through `planInto`
per issue so a finished issue is not dragged back out of Done, so the observer already sees
every one.

The event is `ShouldDispatchAfterCommit`. All of these run inside transactions, and a change
that rolls back must not have been announced.

## Channel authorization

One rule in `routes/channels.php`: you may listen to `organizations.{id}` if you are a member
of that organization.

Project-level visibility is deliberately not checked. The signal carries nothing, and the
reload it triggers re-applies `projects()->visibleTo($user)` on the server. The worst case is
a user being woken by a change to an issue they cannot see, whose reload returns the props
they already had.

## The client

One composable, `resources/js/composables/useLiveIssues.ts`, taking the prop names the calling
page wants refreshed:

```ts
useLiveIssues(['issues', 'sprints'])
```

It subscribes to the organization channel and answers with `router.reload({ only })`, debounced
around 300ms so that a burst settles into one request.

It is guarded with `!import.meta.env.SSR && broadcastingEnabled`, matching `NotificationsMenu`.
Echo connects synchronously during setup, and server rendering has neither a key nor a
WebSocket.

Callers:

| Page | Props reloaded |
| --- | --- |
| `issues/Index.vue` | `issues`, `sprints` |
| `issues/Show.vue` | `issue`, `links`, `activity` |
| `sprints/Show.vue` | `sprint`, `issues` |

`sprints/Show.vue` is the kanban board. There is no separate team board page.

## The socket id

Inertia v3 removed axios, and its XHR client has no notion of a socket id, so
`broadcast()->toOthers()` would silently exclude nobody. Every actor would then reload in
response to their own change, undoing the optimistic updates on the issue index.

A single `http.onRequest` interceptor in `app.ts` stamps `X-Socket-Id` from `Echo.socketId()`.
The interceptor covers the router, `useForm`, `<Form>` and `useHttp`, which is every request
Inertia makes. Laravel reads that header itself.

When Echo has not finished connecting, `socketId()` is undefined and the header is omitted. The
actor then receives their own event and reloads once, which is wasteful but correct.

## Testing

None of it requires a running Reverb.

- Updating an issue dispatches `IssuesChanged` carrying that id and organization. `Event::fake`.
- Reordering a list dispatches one event carrying every id it was given, not one event each,
  and writes the positions in a single query.
- Completing a sprint dispatches an event for the issues it returned to the backlog, proving
  the bulk paths are not silent.
- A transaction that rolls back dispatches nothing.
- The event broadcasts on the private channel for its organization, under the expected name.
- `/broadcasting/auth` refuses a user who is not a member of the organization, and admits one
  who is. This is the test that matters; the rest are shape assertions.

## Costs accepted

The `issues` prop on the index measured 203 KB for 512 issues. Every live update on that page
costs a request returning it. Debouncing absorbs bursts from one actor but not several people
working at once. This ships as designed and gets measured rather than pre-optimized.

If it does prove painful, the fix does not touch any component: the channel becomes per-team
or per-project instead of per-organization, so people are only woken by things they can see.
The listener shape is unchanged.

## Deliberately not in this design

- **Comments and typing indicators.** Typing is a client event whispered on a presence channel,
  which is a different mechanism with a different lifecycle. Sub-project 2.
- **The public portal.** Public channels, logged-out visitors, and payload discipline that
  matters far more when anyone can subscribe. Sub-project 3.
- **Token-based channel authorization.** The planned desktop and mobile clients are Tauri
  wrapping this same frontend, so they carry the session cookie `/broadcasting/auth` relies on
  and need nothing extra. Only a client that is not a webview would, and the planned MCP server
  calls tools rather than subscribing. Left alone until something actually needs it.
- **Broadcasting anything other than issues.** Organizations, teams, projects and sprints do
  not broadcast yet.
- **User-facing bulk editing.** There is no multi-select and no bulk endpoint today. When one
  arrives it follows the pattern the three paths above establish: write in bulk, dispatch once
  with the ids touched.
