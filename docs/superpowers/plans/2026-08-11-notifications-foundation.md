# Notifications Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up broadcasting, the notifications table, channel authorization, and presence lookup — everything the notification feed needs before a single notification is produced.

**Architecture:** Herd already runs Reverb as a shared service on port 8080, so Quill installs no WebSocket server. The framework's own `reverb` broadcast driver delegates to the Pusher driver, so one composer dependency (`pusher/pusher-php-server`) plus `laravel-echo`/`pusher-js` is the whole install. Three channels carry everything: a private per-user channel for notifications, a per-user presence channel that answers "is this person connected?" for email suppression, and a presence channel per organization for who-is-here.

**Tech Stack:** PHP 8.5, Laravel 13, MySQL 9.4 at runtime, SQLite `:memory:` in tests, Pest 5, PHPStan (larastan) level 7, Pint, Inertia v3 + Vue, Vite.

## Global Constraints

- PHPStan stays at **level 7**. Do not raise it. (`.ai/rules/general.md`)
- Models carry **exactly one docblock**: `/** @mixin IdeHelperX */`. (`.ai/rules/models.md`)
- `protected $guarded`, never `$fillable`. `#[UseFactory(...)]` + `HasFactory`.
- No `@return` docblocks on relation methods; `morphTo()` is the sole exception.
- Curly braces on all control structures. Explicit return types and parameter type hints everywhere.
- **Every domain table is UUID v7-keyed.** Migrations use `uuid('id')->primary()` and `foreignUuid`/`uuidMorphs`. Never `id()`, `foreignId()`, or `morphs()`.
- **Tests run on SQLite; verify every migration against MySQL by hand.** SQLite does not enforce column types, so a `uuidMorphs`/`bigint` mismatch passes a green suite and fails only on MySQL. Run `php artisan migrate:fresh` against the real `quill` schema before trusting green tests. (`.ai/rules/migrations.md`)
- **Do not install `laravel/reverb`.** Herd runs the server; the framework provides the `reverb` driver.
- **Do not touch `composer run dev` or `php artisan dev`.** The author wires Reverb into the dev experience; Herd already runs it as a service.
- Herd's Reverb credentials, used verbatim locally: app id `1001`, key `laravel-herd`, secret `secret`, host `127.0.0.1`, port `8080`, scheme `http`.
- Run `vendor/bin/pint --dirty --format agent` before every commit.
- Full gate is `composer ci:check`. Individual runs use `php artisan test --compact --filter=...`.
- **This plan produces no user-visible feature.** No notifications are sent and no UI is added; those are deliverables 5–7 in a later plan.

---

## Why not `php artisan install:broadcasting`

The installer exists and would do much of Task 1, but it prompts to install
Reverb, writes `resources/js/echo.js` into a TypeScript project, and generates a
channels file containing an **authorization bypass** under UUID keys (see Task 3).
Every part of its output needs correcting, so the work is done explicitly instead.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `config/broadcasting.php` | Published framework default; the `reverb` connection reads `REVERB_*` |
| `bootstrap/app.php` | `->withBroadcasting()` registering the channels file |
| `routes/channels.php` | Authorization for all three channels |
| `resources/js/echo.ts` | Echo client, `broadcaster: 'reverb'`, pointed at Herd |
| `resources/js/app.ts` | Imports `echo.ts` so Echo exists before Inertia mounts |
| `database/migrations/..._create_notifications_table.php` | `notifications` with `uuidMorphs` |
| `app/Contracts/PresenceLookup.php` | One-method interface: is this user connected? |
| `app/Support/ReverbPresenceLookup.php` | Real implementation over the Pusher SDK, fail-open |
| `app/Support/FakePresenceLookup.php` | Test double with a settable answer |
| `tests/Feature/Notifications/BroadcastingConfigTest.php` | Config resolves; auth route exists |
| `tests/Feature/Notifications/ChannelAuthorizationTest.php` | Who may subscribe to what |
| `tests/Feature/Notifications/NotificationsTableTest.php` | A notification can be stored and read |
| `tests/Feature/Notifications/PresenceLookupTest.php` | Fail-open and fake behaviour |

---

### Task 1: Broadcasting dependencies, config, and the Echo client

**Files:**
- Modify: `composer.json`, `package.json` (via install commands)
- Create: `config/broadcasting.php` (published)
- Modify: `bootstrap/app.php`
- Create: `routes/channels.php`
- Create: `resources/js/echo.ts`
- Modify: `resources/js/app.ts`
- Modify: `.env`, `.env.example`, `phpunit.xml`
- Test: `tests/Feature/Notifications/BroadcastingConfigTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `config('broadcasting.default') === 'reverb'`; a resolvable broadcaster;
  a `POST /broadcasting/auth` route; `window.Echo` in the browser.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Notifications/BroadcastingConfigTest.php`:

```php
<?php

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

test('the default broadcast connection is reverb', function () {
    expect(config('broadcasting.default'))->toBe('reverb');
});

test('the reverb connection resolves to a pusher broadcaster', function () {
    expect(Broadcast::connection('reverb'))->toBeInstanceOf(PusherBroadcaster::class);
});

test('the reverb connection points at herd', function () {
    $options = config('broadcasting.connections.reverb.options');

    expect($options['host'])->toBe('127.0.0.1');
    expect((int) $options['port'])->toBe(8080);
    expect($options['scheme'])->toBe('http');
    expect($options['useTLS'])->toBeFalse();
});

test('the broadcasting auth route is registered', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri());

    expect($uris)->toContain('broadcasting/auth');
})->note('The framework registers this route unnamed, so it is found by URI, not by name.');
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=BroadcastingConfigTest`
Expected: FAIL — `config('broadcasting.default')` is null, because
`config/broadcasting.php` does not exist.

- [ ] **Step 3: Install the dependencies**

```bash
composer require pusher/pusher-php-server
npm install --save-dev laravel-echo pusher-js
```

Expected: `pusher/pusher-php-server` at `^7.3`, and both npm packages added.
**Do not** install `laravel/reverb`.

- [ ] **Step 4: Publish the broadcasting config**

```bash
php artisan config:publish broadcasting
```

The published file already contains a `reverb` connection reading `REVERB_APP_KEY`,
`REVERB_APP_SECRET`, `REVERB_APP_ID`, `REVERB_HOST`, `REVERB_PORT` and
`REVERB_SCHEME`. **Leave the file unedited** — the values come from the
environment.

- [ ] **Step 5: Register broadcasting in the application bootstrap**

In `bootstrap/app.php`, add a `withBroadcasting` call between `withRouting` and
`withMiddleware`:

```php
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
    )
```

- [ ] **Step 6: Create the channels file**

Create `routes/channels.php`. Real authorization arrives in Task 3; this is the
minimum that lets the auth route resolve:

```php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, string $id) {
    return false;
});
```

> Returning `false` is deliberate: until Task 3 writes real checks, denying
> everything is the safe placeholder. Never ship the framework's stub version —
> see Task 3.

- [ ] **Step 7: Set the environment values**

Append to both `.env` and `.env.example`, and change the existing
`BROADCAST_CONNECTION=log` line to `reverb` in both:

```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=1001
REVERB_APP_KEY=laravel-herd
REVERB_APP_SECRET=secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

These are Herd's shared local development credentials, deliberately committed to
`.env.example`. Production needs its own Reverb and its own secret.

- [ ] **Step 8: Pin the test environment**

Tests must not depend on a developer's `.env`. In `phpunit.xml`, inside the
existing `<php>` block alongside `DB_CONNECTION`, add:

```xml
        <env name="BROADCAST_CONNECTION" value="reverb"/>
        <env name="REVERB_APP_ID" value="1001"/>
        <env name="REVERB_APP_KEY" value="testing-key"/>
        <env name="REVERB_APP_SECRET" value="testing-secret"/>
        <env name="REVERB_HOST" value="127.0.0.1"/>
        <env name="REVERB_PORT" value="8080"/>
        <env name="REVERB_SCHEME" value="http"/>
```

Signing a channel authorization response is local HMAC, so no Reverb process is
needed for tests to pass.

- [ ] **Step 9: Write the Echo client**

Create `resources/js/echo.ts`. This is the reverb-flavoured client config, in
TypeScript, with TLS off because Herd serves the socket over plain http:

```ts
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
    interface Window {
        Pusher: typeof Pusher
        Echo: Echo<'reverb'>
    }
}

window.Pusher = Pusher

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
})
```

- [ ] **Step 10: Import Echo before Inertia mounts**

In `resources/js/app.ts`, add the import as the first line so `window.Echo` exists
before any component tries to subscribe:

```ts
import '@/echo'
```

- [ ] **Step 11: Run the test to verify it passes**

Run: `php artisan test --compact --filter=BroadcastingConfigTest`
Expected: PASS, 4 tests.

- [ ] **Step 12: Confirm the frontend still builds and types**

```bash
npm run types:check
npm run build
```

Expected: both clean. If `vue-tsc` complains about `Echo<'reverb'>`, check the
installed `laravel-echo` version's exported generics rather than loosening the
type to `any`.

- [ ] **Step 13: Run the full gate and commit**

```bash
vendor/bin/pint --dirty --format agent
composer ci:check
git add -A
git commit -m "Point broadcasting at the Reverb service Herd runs"
```

---

### Task 2: The notifications table

**Files:**
- Create: `database/migrations/2026_08_12_000001_create_notifications_table.php`
- Test: `tests/Feature/Notifications/NotificationsTableTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: a `notifications` table whose `notifiable_id` is `char(36)`, so
  `$user->notifications` works and `Notifiable::notify()` can persist.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Notifications/NotificationsTableTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('the notifiable id is a uuid column, not an integer', function () {
    expect(Schema::hasTable('notifications'))->toBeTrue();
    expect(Schema::getColumnType('notifications', 'notifiable_id'))->not->toBe('integer');
    expect(Schema::getColumnType('notifications', 'notifiable_id'))->not->toBe('bigint');
});

test('a notification row can be stored against a user and read back', function () {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid7(),
        'type' => 'App\\Notifications\\Teams\\TeamInvitation',
        'data' => ['organization_name' => 'NotaryDash'],
    ]);

    $notification = $user->fresh()->notifications->first();

    expect($notification)->not->toBeNull();
    expect($notification->data['organization_name'])->toBe('NotaryDash');
    expect($notification->notifiable_id)->toBe($user->id);
    expect($notification->read_at)->toBeNull();
});

test('a notification can be marked read', function () {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid7(),
        'type' => 'App\\Notifications\\Teams\\TeamInvitation',
        'data' => [],
    ]);

    $user->unreadNotifications->first()->markAsRead();

    expect($user->fresh()->unreadNotifications)->toBeEmpty();
    expect($user->fresh()->notifications)->toHaveCount(1);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=NotificationsTableTest`
Expected: FAIL — `expect(false)->toBeTrue()`, the table does not exist.

- [ ] **Step 3: Write the migration**

The framework stub for this table declares `$table->morphs('notifiable')`, which
is **`bigint`** and cannot reference Quill's `char(36)` users. Write it by hand
with `uuidMorphs` instead of publishing the stub:

Create `database/migrations/2026_08_12_000001_create_notifications_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel's published stub uses `morphs('notifiable')`, which is bigint. Every
     * notifiable in this application is UUID-keyed, so the morph must be too.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=NotificationsTableTest`
Expected: PASS, 3 tests.

- [ ] **Step 5: Verify the migration against real MySQL**

A green SQLite suite proves nothing about column types. Run it against `quill`:

```bash
php artisan migrate
php artisan tinker --execute '
$db = config("database.connections.mysql.database");
foreach (DB::select("SELECT column_name AS c, column_type AS ty
    FROM information_schema.columns
    WHERE table_schema = ? AND table_name = \"notifications\"
    ORDER BY ordinal_position", [$db]) as $r) {
    echo str_pad($r->c, 20).$r->ty.PHP_EOL;
}'
```

Expected: `id` and `notifiable_id` both `char(36)`; `notifiable_type` `varchar(255)`.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations tests/Feature/Notifications/NotificationsTableTest.php
git commit -m "Add the notifications table with UUID notifiables"
```

---

### Task 3: Channel authorization

This task fixes a real authorization bypass. The framework's channels stub is:

```php
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;      // WRONG under UUIDs
});
```

`(int) '019ff1ce-6167-724a-…'` is **`0`**, so `0 === 0` is true for every user
against every other user's channel. Shipped as-is, any authenticated user could
subscribe to anybody's notification stream. The comparison must be string
identity.

**Files:**
- Modify: `routes/channels.php`
- Test: `tests/Feature/Notifications/ChannelAuthorizationTest.php`

**Interfaces:**
- Consumes: `withBroadcasting` and the auth route (Task 1); `belongsToOrganization`
  from `App\Concerns\HasOrganizations`.
- Produces: authorization for `App.Models.User.{id}` and `organizations.{organizationId}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Notifications/ChannelAuthorizationTest.php`:

```php
<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

function authorizeChannel(User $user, string $channel): \Illuminate\Testing\TestResponse
{
    return test()
        ->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);
}

test('a user may subscribe to their own notification channel', function () {
    $user = User::factory()->create();

    authorizeChannel($user, 'private-App.Models.User.'.$user->id)->assertOk();
});

test('a user may not subscribe to another users notification channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    authorizeChannel($user, 'private-App.Models.User.'.$other->id)->assertForbidden();
})->note('Guards the (int) cast bypass: every UUID casts to 0, so a loose comparison authorizes everyone.');

test('a guest may not subscribe to a notification channel', function () {
    $user = User::factory()->create();

    $this
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])
        ->assertUnauthorized();
});

test('a member may join their organizations presence channel', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($user, ['role' => OrganizationRole::Member->value]);

    authorizeChannel($user, 'presence-organizations.'.$organization->id)->assertOk();
});

test('a client contact may join their own organizations presence channel', function () {
    $contact = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($contact, ['role' => OrganizationRole::Client->value]);

    authorizeChannel($contact, 'presence-organizations.'.$organization->id)->assertOk();
});

test('a non member may not join an organizations presence channel', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    authorizeChannel($user, 'presence-organizations.'.$organization->id)->assertForbidden();
});

test('a user may join their own presence channel', function () {
    $user = User::factory()->create();

    authorizeChannel($user, 'presence-users.'.$user->id)->assertOk();
})->note('This is the channel the presence lookup reads occupancy from.');

test('a user may not join another users presence channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    authorizeChannel($user, 'presence-users.'.$other->id)->assertForbidden();
});

test('the presence payload carries the members name for display', function () {
    $user = User::factory()->create(['name' => 'Karl Murray']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($user, ['role' => OrganizationRole::Owner->value]);

    $response = authorizeChannel($user, 'presence-organizations.'.$organization->id);

    $response->assertOk();

    expect($response->json('channel_data'))->toContain('Karl Murray');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=ChannelAuthorizationTest`
Expected: FAIL — the own-channel test 403s (Task 1 denies everything) and the
presence channel is not registered at all.

- [ ] **Step 3: Write the authorization**

Replace `routes/channels.php` entirely:

```php
<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Compared as strings. Laravel's published stub casts both sides with `(int)`,
 * which evaluates every UUID to 0 and authorizes every user for every other
 * user's channel.
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return hash_equals($user->id, $id);
});

/**
 * A per-user presence channel whose only purpose is to answer "is this person
 * connected right now?" for email suppression. Nothing subscribes to it until
 * deliverable 6; authorizing it here keeps the lookup and its channel together.
 */
Broadcast::channel('users.{id}', function (User $user, string $id): ?array {
    if (! hash_equals($user->id, $id)) {
        return null;
    }

    return ['id' => $user->id];
});

/**
 * Presence of members within one organization. A Client-role contact belongs to
 * the organization and is admitted deliberately — they are a member.
 */
Broadcast::channel('organizations.{organizationId}', function (User $user, string $organizationId): ?array {
    $organization = Organization::find($organizationId);

    if ($organization === null || ! $user->belongsToOrganization($organization)) {
        return null;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
```

> A presence callback returns the payload other subscribers see, or `null` to
> refuse. Returning `false` from a presence channel is not the refusal contract.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ChannelAuthorizationTest`
Expected: PASS, 9 tests.

- [ ] **Step 5: Prove the bypass is actually closed**

Temporarily change the user channel body to the framework's version
(`return (int) $user->id === (int) $id;`), then run:

Run: `php artisan test --compact --filter=ChannelAuthorizationTest`
Expected: FAIL on `a user may not subscribe to another users notification channel`.

Restore `hash_equals` and confirm the suite passes again. This proves the test
actually guards the bug rather than passing incidentally.

- [ ] **Step 6: Run the full gate and commit**

```bash
vendor/bin/pint --dirty --format agent
composer ci:check
git add routes/channels.php tests/Feature/Notifications/ChannelAuthorizationTest.php
git commit -m "Authorize notification and organization presence channels"
```

---

### Task 4: Presence lookup

**Files:**
- Create: `app/Contracts/PresenceLookup.php`
- Create: `app/Support/ReverbPresenceLookup.php`
- Create: `app/Support/FakePresenceLookup.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Notifications/PresenceLookupTest.php`

**Interfaces:**
- Consumes: the `reverb` broadcast connection (Task 1).
- Produces:
  - `App\Contracts\PresenceLookup::isOnline(User $user): bool`
  - `App\Support\ReverbPresenceLookup` bound as the default implementation
  - `App\Support\FakePresenceLookup::__construct(bool $online = false)` with
    `->setOnline(bool $online): void`, for tests and for later use by the
    producers in deliverable 5.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Notifications/PresenceLookupTest.php`:

```php
<?php

use App\Contracts\PresenceLookup;
use App\Models\User;
use App\Support\FakePresenceLookup;
use App\Support\ReverbPresenceLookup;

test('the container resolves the reverb implementation by default', function () {
    expect(app(PresenceLookup::class))->toBeInstanceOf(ReverbPresenceLookup::class);
});

test('the fake reports whatever it was told', function () {
    $user = User::factory()->create();

    $fake = new FakePresenceLookup(online: true);
    expect($fake->isOnline($user))->toBeTrue();

    $fake->setOnline(false);
    expect($fake->isOnline($user))->toBeFalse();
});

test('the fake can be swapped into the container', function () {
    $user = User::factory()->create();

    app()->instance(PresenceLookup::class, new FakePresenceLookup(online: true));

    expect(app(PresenceLookup::class)->isOnline($user))->toBeTrue();
});

test('an unreachable reverb reports the user as offline so the email still sends', function () {
    $user = User::factory()->create();

    config()->set('broadcasting.connections.reverb.options.port', 1);

    expect(app(ReverbPresenceLookup::class)->isOnline($user))->toBeFalse();
})->note('Fail-open: a WebSocket outage must cost a redundant email, never a swallowed notification.');
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=PresenceLookupTest`
Expected: FAIL — `Class "App\Contracts\PresenceLookup" not found`.

- [ ] **Step 3: Write the contract**

Create `app/Contracts/PresenceLookup.php`:

```php
<?php

namespace App\Contracts;

use App\Models\User;

interface PresenceLookup
{
    /**
     * Whether the user currently has a live WebSocket connection.
     *
     * Implementations must fail open: if the answer cannot be determined, report
     * the user as offline so that fallback channels such as email still fire.
     */
    public function isOnline(User $user): bool;
}
```

- [ ] **Step 4: Write the Reverb implementation**

The user's presence is read from the per-user presence channel's occupancy.
`PusherBroadcaster::getPusher()` hands back a configured `Pusher\Pusher`, so the
credentials and host come from `config/broadcasting.php` with nothing duplicated.

Create `app/Support/ReverbPresenceLookup.php`:

```php
<?php

namespace App\Support;

use App\Contracts\PresenceLookup;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReverbPresenceLookup implements PresenceLookup
{
    public function isOnline(User $user): bool
    {
        try {
            $broadcaster = Broadcast::connection('reverb');

            if (! $broadcaster instanceof PusherBroadcaster) {
                return false;
            }

            $channel = $broadcaster->getPusher()->getChannelInfo(
                'presence-users.'.$user->id,
                ['info' => 'user_count'],
            );

            return (int) ($channel->user_count ?? 0) > 0;
        } catch (Throwable $e) {
            Log::warning('Presence lookup failed; assuming the user is offline.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
```

> **Confirm `getChannelInfo` against the installed SDK.** `pusher/pusher-php-server`
> 7.x exposes it; if the method name differs in the resolved version, adjust here
> and nowhere else — that isolation is the reason this class exists. The endpoint
> itself is verified working: `GET http://127.0.0.1:8080/apps/1001/channels`
> returns `200 {"channels":[]}`.

- [ ] **Step 5: Write the fake**

Create `app/Support/FakePresenceLookup.php`:

```php
<?php

namespace App\Support;

use App\Contracts\PresenceLookup;
use App\Models\User;

class FakePresenceLookup implements PresenceLookup
{
    public function __construct(protected bool $online = false) {}

    public function isOnline(User $user): bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }
}
```

- [ ] **Step 6: Bind the contract**

In `app/Providers/AppServiceProvider.php`, bind the interface inside the existing
empty `register()` method:

```php
    public function register(): void
    {
        $this->app->singleton(PresenceLookup::class, ReverbPresenceLookup::class);
    }
```

Add the imports `use App\Contracts\PresenceLookup;` and
`use App\Support\ReverbPresenceLookup;`.

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PresenceLookupTest`
Expected: PASS, 4 tests. The fail-open test makes a real connection attempt to
port 1 and must not hang — if it does, the Pusher client needs an explicit
timeout in `client_options` and the test should assert it completes.

- [ ] **Step 8: Confirm it reports true against the running service**

This is the one check that needs Herd's Reverb and a real browser connection, so
it is manual and not a test. With `npm run dev` running and the app open in a
browser, subscribe from the console and ask:

```js
window.Echo.join('users.' + '<your-user-uuid>')
```

```bash
php artisan tinker --execute 'echo app(App\Contracts\PresenceLookup::class)->isOnline(App\Models\User::where("email","karl@vheissulabs.com")->firstOrFail()) ? "online" : "offline";'
```

Expected: `online` once the browser has joined. The channel is authorized by
Task 3, but nothing in the UI joins it until deliverable 6 — so joining by hand in
the console, as above, is the only way to see `online` at this stage.

- [ ] **Step 9: Run the full gate and commit**

```bash
vendor/bin/pint --dirty --format agent
composer ci:check
git add app/Contracts app/Support app/Providers/AppServiceProvider.php tests/Feature/Notifications/PresenceLookupTest.php
git commit -m "Add presence lookup with a fail-open Reverb implementation"
```

---

## What this plan deliberately does not do

Deliverables 5–7 from the spec, each getting its own plan:

5. The three producers (`Teams\TeamInvitation` gaining `database`/`broadcast`,
   plus `TeamRoleChanged` and `RemovedFromTeam`), with `via()` consulting
   `PresenceLookup`.
6. Shared page props, the sidebar bell row, the grouped popover, and Echo wiring
   in the UI. **This is also where something first subscribes to
   `presence-users.{id}`**, which is what makes Task 4's lookup return true.
7. Mark-read and mark-all-read endpoints and their policies.

## Notes for the reviewer

- **Nothing here is user-visible.** The measure of success is a green gate, a
  `char(36)` `notifiable_id` in MySQL, and channel authorization that rejects the
  wrong user. Resist the urge to add a notification to "see it work" — that is
  deliverable 5.
- **Two corrections to the spec** were found while writing this plan and should be
  folded back into it: the `reverb` broadcast driver is provided by the framework
  (`BroadcastManager::createReverbDriver`, which delegates to the Pusher driver),
  not by `laravel/reverb`; and `laravel-echo` supports `broadcaster: 'reverb'`
  client-side. Neither changes the dependency list.
- **Three channels, three jobs.** `private-App.Models.User.{id}` carries
  notifications; `presence-users.{id}` exists only so the lookup can ask whether
  someone is connected; `presence-organizations.{id}` is who-is-here within an
  organization. All three are authorized in Task 3. Only the first is subscribed
  to before deliverable 6, which is why Task 4's manual check needs a console
  `join()`.
