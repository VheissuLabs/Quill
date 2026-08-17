# Permissions Only

**Date:** 2026-08-13
**Status:** Designed, not built

Code names permissions and never roles. `$user->can('client:create')` is the only
authorization idiom in the application.

## The rule

A role is a **named bundle of permissions inside one organization** — data an owner
edits, and eventually a settings screen. Engineers own the permission catalog and
nothing else. No branch anywhere in `app/`, `routes/`, or `resources/js/` may ask
what role someone holds.

That single rule is what the rest of this document falls out of.

## The scope is the organization

Spatie's teams feature stays, with `team_foreign_key` pointing at
`organization_id` as it already does. One middleware sets the scope from
`$user->current_organization_id` once per request and never changes it, which is
what makes a bare `$user->can()` correct without an organization argument.

`withinOrganization()` survives as an internal helper for seeders, console
commands and queued jobs, where there is no request to read the current
organization from. It is a scope switch, not a role check.

### Two rejected alternatives

**A polymorphic scope we own.** A typed `scope_type`/`scope_id` pair reading either
an organization or a team, replacing Spatie's four tables with `roles`,
`role_permissions` and a polymorphic `permissions` grant table. Spatie cannot
express this — `PermissionRegistrar` reads one untyped `team_foreign_key` column and
the package has no type column anywhere — so adopting it means owning the `Gate`
wiring, the resolution queries and cache invalidation. What that complexity buys is
per-team grants, and we decided we do not want them. Rejected on cost.

**One untyped column holding both.** Keep Spatie, and let `organization_id` hold an
organization id on organization roles and a team id on team roles. They are both
UUIDs so they never collide, but the schema cannot say which is which, and every
request would have to switch scope depending on the route, with closures wherever a
page needed both at once. Rejected on the same grounds.

## No per-team grants

`team:update` means "may update teams in this organization". Nobody is an admin of
Engineering and a plain member of Delivery.

This deletes a working system rather than porting it: `TeamRole`,
`TeamPermission`, the `team_members.role` column, `teamRole()`, `ownsTeam()`,
`hasTeamPermission()`, `toTeamPermissions()`, and the `level()` / `isAtLeast()`
hierarchy in both role enums. `EnsureTeamMembership` keeps checking membership and
loses its minimum-role argument. Team invitations stop carrying a role: they grant
membership, and what a member may do comes from the organization.

**This is a real behavior change, not a refactor.** Anyone holding `team:update`
can now update every team in the organization. If per-team authority becomes a
requirement, it arrives as team-scoped grants against a polymorphic scope — the
alternative rejected above — and this decision is where to revisit.

## One permission catalog

`OrganizationPermission` and `TeamPermission` merge into `App\Enums\Permission`.
Six names collided across the two enums (`member:add`, `member:update`,
`member:remove`, `invitation:create`, `invitation:cancel`, `team:update`); with one
scope there is one meaning for each, so the merge is a deduplication rather than a
rename.

Spatie permissions are global rows keyed by `(name, guard_name)`. Roles are
per-organization. That split is already what the code does and does not change.

## Roles become data

The default bundles move out of `OrganizationRole::permissions()` into
`config/roles.php`, read by `SeedDefaultRoles` when an organization is created.
Owner, Admin, Member and Client stay as the seeded defaults — an owner may then
rename them, change what they grant, or add their own.

`OrganizationRole` and `TeamRole` are deleted. Two places still carry a role *name*
as a string, which is data and not a check:

- `organization_invitations.role` — the bundle the invitation grants, validated
  against the roles that exist in that organization rather than against an enum.
- `UserOrganization.role` / `roleLabel` in the Inertia props — displayed to the
  user. Nothing branches on it.

## Ownership is a column, not a role

`Organization::owner()` and `Team::owner()` currently find whoever holds the Owner
role. Both become real `owner_id` columns, because a role lookup is exactly what
this design forbids and because ownership must survive an owner renaming their own
role bundles in settings.

- `organizations.owner_id` — non-null, the user who created it.
- `teams.owner_id` — non-null; for a personal team, its user.

An observer holds the invariant. `membersWithRole()` is deleted.

## Contacts come from the membership

`isClientContact()` is replaced by the membership's `client_id` being non-null,
which is what a contact already is: `organization_members.client_id` records the
client a contact represents. `DenyClientContacts` reads that.

`ownsOrganization()` is deleted; nothing needs it once ownership is a column and
authorization is a permission.

## Policies keep only what a permission cannot say

Spatie registers a `Gate::before` hook, so any policy method that merely forwarded
to a permission is already dead weight — the hook answers first. Those methods go,
and controllers, form requests and routes check the permission directly.

Policies keep the rules that depend on the model rather than the actor's grants:
`view` (does this record belong to an organization I am in), `leave`, and "a
personal team cannot be deleted".

## The frontend gets a list, not a struct

`OrganizationPermissions` (ten booleans) and `TeamPermissions` (seven) are replaced
by a flat array of granted permission strings on the shared props, plus a `can()`
helper in TypeScript. Adding a permission then costs nothing on the client; today
it costs two fields and a type.

## Migrations are rewritten in place

Only local databases have run these, so the change is made where the columns are
declared rather than in rename-and-backfill migrations carried forever:

| Migration | Change |
| --- | --- |
| `2026_01_27_000001_create_teams_table` | Drop `role` from `team_members` and from `team_invitations` |
| `2026_08_11_000004_add_ownership_to_teams_table` | Add `owner_id` — it already carries the other ownership columns |
| `2026_08_11_000001_create_organizations_table` | Add `owner_id` to `organizations` |
| `2026_08_12_000004_create_organization_invitations_table` | `role` stays a string; the enum cast is dropped in the model |
| `2026_08_11_220746_create_permission_tables` | Unchanged — the scope stays `organization_id` |

`team_invitations.role` goes with the team role system. A team invitation grants
membership and nothing else, so `TeamInvitation`, its factory, the invitation
request and the accept path all lose their role argument.

`migrate:fresh --seed` is the upgrade path. Per `.ai/rules/migrations.md`, run it
against the real MySQL schema and not only the SQLite suite.

## Build order

Each step leaves the app usable in a browser and the suite green.

1. **`App\Enums\Permission` and the scope middleware.** Policies and requests move
   to `$user->can()`. Roles are still seeded from the enum, so nothing else moves
   yet.
2. **Ownership columns.** `owner_id` on both tables, observer, seeders and
   factories updated, `membersWithRole()` and `ownsOrganization()` deleted.
3. **`config/roles.php`.** Bundles become data; `OrganizationRole` deleted;
   invitations carry a validated role name.
4. **Delete the team role system.** `TeamRole`, `TeamPermission`,
   `team_members.role`, the `HasTeams` role helpers, `EnsureTeamMembership`'s
   minimum role.
5. **Frontend `can()`.** Granted-permission list on the shared props, DTOs and
   their TypeScript types deleted.
6. **Contacts from membership data.** `DenyClientContacts` reads `client_id`;
   `isClientContact()` deleted.

## Testing

Behaviour tests grant explicit permission lists, so each test states what it needs
instead of naming a role. The Pest helper `organizationWith(OrganizationRole::Admin)`
becomes `organizationWith(['client:create', 'team:create'])`.

- A user granted `client:create` may create a client; one without it is refused.
- A grant in one organization does not apply in another — the scope boundary.
- The seeded default bundles grant what `config/roles.php` says, asserted once
  rather than in every test.
- `owner_id` is set on create for organizations, teams and personal teams, and an
  observer refuses null.
- A contact (membership with `client_id`) cannot reach the assistant; staff can.
- A user with `team:update` may update any team in the organization — the
  behaviour change, asserted deliberately so it is not mistaken for a bug.
- Nothing in `app/` references a role name: an architecture test greps for the
  deleted enums so the rule cannot rot.

## Failure modes

- **The scope is unset.** Any check outside a request — a queued job, a command, a
  test that forgets the helper — resolves against no organization and silently
  returns false. The middleware covers requests; `withinOrganization()` covers the
  rest; the test helper sets it.
- **Stale permission cache.** Spatie caches per scope. Anything that changes a
  grant calls `forgetCachedPermissions()`, as the current code already does.
- **A renamed role orphans an invitation.** An invitation stores a role name; an
  owner may rename that role before it is accepted. Acceptance validates the name
  still exists and fails loudly rather than granting nothing.

## Sequencing

This lands on top of the assistant branch, which is unmerged and touches the same
policies, migrations and seeders. Merge PR #1 first; this work rebases onto `main`
afterwards.
