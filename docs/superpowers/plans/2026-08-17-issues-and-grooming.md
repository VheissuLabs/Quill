# Issues and the Grooming PM Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A client contact describes what they want, the assistant grooms it into a real issue, and it lands in that client's project once they confirm — while staff get issues on the project page and the same tool.

**Architecture:** `Issue` and `IssueDraft` are ordinary Eloquent models scoped by `organization_id`. Issue types are per-organization rows copied from unscoped templates, so no code ever names a type. `create_issue` writes a draft, never an issue; confirming the draft promotes it. Contacts reach the existing assistant with exactly one tool granted.

**Tech Stack:** Laravel 12 / PHP 8.5, Inertia v3 + Vue 3, `laravel/ai` with a fake driver in tests, `spatie/laravel-permission` (permissions are rows, scope is the organization), Pest, Wayfinder, Tailwind.

**Spec:** `docs/superpowers/specs/2026-08-17-issues-and-grooming-design.md`

## Global Constraints

- **No code may name an issue type.** `bug`, `feature`, `enhancement` appear once in the codebase, in `IssueTypeSeeder`. No enum, no config array, no constant, no `match` on a type name, no test asserting the string `bug`.
- **No enums for data.** `app/Enums` does not exist and must not be recreated. State is timestamps (`closed_at`, `discarded_at`) or rows.
- **Authorization goes on routes** (`->middleware('can:issue:create')`), never `Gate::authorize()` in a controller. Spatie registers `Gate::before`, so a permission name works directly as `can:` middleware.
- **Validation goes in form requests**, never `$request->validate()` in a controller.
- **Controllers use standard actions only** — `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`.
- **Models:** `protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at']`, `#[UseFactory(XFactory::class)]`, one docblock only (`/** @mixin IdeHelperX */`), no `@return` on relations except `morphTo()`.
- **Every migration is rewritten in place if not yet shipped**; this plan adds new migrations only. Run `php artisan migrate:fresh --seed` against real MySQL before trusting green SQLite tests (`.ai/rules/migrations.md`).
- **Streamed agent tests:** call `->streamedContent()` to drain the stream or nothing persists, and key fakes on the prompt (`Agent::fake(fn (string $prompt) => match ($prompt) { … })`) — positional array fakes break with streaming (`.ai/rules/ai.md`).
- **After any PHP change:** `vendor/bin/pint --dirty --format agent`. Before finishing a task: `composer ci:check`.
- **Wayfinder:** regenerate with `php artisan wayfinder:generate --with-form` (a bare run drops `.form()` helpers and breaks `vue-tsc`).
- **This plan starts after PR #3 merges** — it builds on `AssistantController`, `ProjectController`, and the route gates from that branch.

## File Structure

| File | Responsibility |
| --- | --- |
| `database/migrations/*_create_issue_types_table.php` | Per-organization type rows; unscoped rows are templates |
| `database/migrations/*_create_issues_table.php` | Issues, with `unique(project_id, number)` |
| `database/migrations/*_create_issue_drafts_table.php` | Drafts awaiting confirmation |
| `app/Models/IssueType.php` | Type row; `archived_at` scope |
| `app/Models/Issue.php` | Issue; allocates its per-project number |
| `app/Models/IssueDraft.php` | Draft; promotes itself to an issue |
| `app/Observers/IssueReporterObserver.php` | A contact's issue must carry their client |
| `app/Actions/Organizations/SeedDefaultIssueTypes.php` | Copies template types into a new organization |
| `database/seeders/IssueTypeSeeder.php` | The only place type names appear |
| `app/Http/Controllers/Projects/IssueController.php` | index (on the project page), show, store |
| `app/Http/Controllers/Projects/IssueClosureController.php` | store (close), destroy (reopen) |
| `app/Http/Controllers/Assistant/IssueDraftController.php` | update (confirm), destroy (discard) |
| `app/Http/Requests/Projects/StoreIssueRequest.php` | Title, description, type validation |
| `app/Ai/Tools/CreateIssue.php` | Writes a draft, never an issue |
| `app/Ai/Tools/ListIssues.php` | Staff-only read tool |
| `app/Ai/Prompts/GroomingRubric.php` | The contact-facing prompt, tuned often |
| `app/Notifications/Issues/GroomingNeedsHuman.php` | Escalation to the organization |
| `resources/js/pages/projects/Show.vue` | Issue list replaces the placeholder |
| `resources/js/pages/issues/Show.vue` | One issue |
| `resources/js/components/IssueDraftCard.vue` | Confirm / not-quite card in the transcript |

---

### Task 1: Issue types, issues, and permissions

**Files:**
- Create: `database/migrations/2026_08_18_000001_create_issue_types_table.php`
- Create: `database/migrations/2026_08_18_000002_create_issues_table.php`
- Create: `app/Models/IssueType.php`, `app/Models/Issue.php`
- Create: `app/Observers/IssueReporterObserver.php`
- Create: `app/Actions/Organizations/SeedDefaultIssueTypes.php`
- Create: `database/seeders/IssueTypeSeeder.php`
- Create: `database/factories/IssueFactory.php`, `database/factories/IssueTypeFactory.php`
- Modify: `app/Observers/OrganizationObserver.php`, `database/seeders/RoleSeeder.php`, `database/seeders/DatabaseSeeder.php`, `app/Models/Organization.php`, `app/Models/Project.php`
- Test: `tests/Feature/Issues/IssueTest.php`, `tests/Feature/Issues/IssueTypeTest.php`

**Interfaces:**
- Produces: `Issue::create(array)` allocating `number`; `Issue::open()` / `Issue::closed()` scopes; `Issue::close(): void`; `Issue::reopen(): void`; `IssueType::active()` scope; `SeedDefaultIssueTypes::handle(Organization): void`; `Organization::issueTypes(): HasMany`; `Project::issues(): HasMany`; permissions `issue:create`, `issue:update`, `issue:close`.

- [ ] **Step 1: Write the failing test for type templates and copies**

`tests/Feature/Issues/IssueTypeTest.php`:

```php
<?php

use App\Models\IssueType;
use App\Models\Organization;
use Database\Seeders\IssueTypeSeeder;

test('the seeded types are unscoped templates and every organization gets a copy', function () {
    new IssueTypeSeeder()->run();

    $templates = IssueType::whereNull('organization_id')->pluck('name');

    expect($templates)->toHaveCount(3);

    $organization = Organization::factory()->create();

    expect(IssueType::where('organization_id', $organization->id)->pluck('name')->sort()->values()->all())
        ->toBe($templates->sort()->values()->all());
})->note('Organizations rename and retire their own types, so each gets a copy rather than a shared row.');

test('a type renamed in one organization leaves the other alone', function () {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    $type = IssueType::where('organization_id', $mine->id)->first();
    $original = $type->name;
    $type->update(['name' => 'Defect']);

    expect(IssueType::where('organization_id', $theirs->id)->pluck('name'))->toContain($original);
});

test('archived types are excluded from the active scope', function () {
    $organization = Organization::factory()->create();
    $type = IssueType::where('organization_id', $organization->id)->first();

    $type->update(['archived_at' => now()]);

    expect(IssueType::active()->where('organization_id', $organization->id)->pluck('id'))
        ->not->toContain($type->id);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact tests/Feature/Issues/IssueTypeTest.php`
Expected: FAIL — `Class "App\Models\IssueType" not found`

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_18_000001_create_issue_types_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A null organization_id is a template every new organization is copied from,
     * the same shape the default roles use.
     */
    public function up(): void
    {
        Schema::create('issue_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_types');
    }
};
```

`database/migrations/2026_08_18_000002_create_issues_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `client_id` is null for internal work: a project owned by a team serves
     * nobody outside the organization. A contact's issue always carries one,
     * which the observer enforces.
     *
     * `conversation_id` has no foreign key — the conversations table belongs to
     * laravel/ai and an issue must outlive a pruned transcript.
     */
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('issue_type_id')->constrained('issue_types')->restrictOnDelete();
            $table->foreignUuid('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('description');
            $table->text('acceptance_criteria')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'number']);
            $table->index(['organization_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
```

- [ ] **Step 4: Write `IssueType`, the seeder, and the copy action**

`app/Models/IssueType.php`:

```php
<?php

namespace App\Models;

use Database\Factories\IssueTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin IdeHelperIssueType */

#[UseFactory(IssueTypeFactory::class)]
class IssueType extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
```

`database/seeders/IssueTypeSeeder.php` — **the only place these words appear:**

```php
<?php

namespace Database\Seeders;

use App\Models\IssueType;
use Illuminate\Database\Seeder;

class IssueTypeSeeder extends Seeder
{
    /**
     * The starting classifications. Organizations rename, reorder and retire
     * their own copies, so nothing in the application may branch on these names.
     *
     * @var array<int, string>
     */
    protected array $types = ['Bug', 'Feature', 'Enhancement'];

    public function run(): void
    {
        foreach ($this->types as $position => $name) {
            IssueType::firstOrCreate(
                ['organization_id' => null, 'name' => $name],
                ['position' => $position],
            );
        }
    }
}
```

`app/Actions/Organizations/SeedDefaultIssueTypes.php`:

```php
<?php

namespace App\Actions\Organizations;

use App\Models\IssueType;
use App\Models\Organization;
use Database\Seeders\IssueTypeSeeder;
use Illuminate\Support\Collection;

class SeedDefaultIssueTypes
{
    public function handle(Organization $organization): void
    {
        foreach ($this->templates() as $template) {
            IssueType::firstOrCreate([
                'organization_id' => $organization->id,
                'name' => $template->name,
            ], [
                'position' => $template->position,
            ]);
        }
    }

    /** @return Collection<int, IssueType> */
    protected function templates(): Collection
    {
        $templates = IssueType::whereNull('organization_id')->orderBy('position')->get();

        if ($templates->isEmpty()) {
            new IssueTypeSeeder()->run();

            $templates = IssueType::whereNull('organization_id')->orderBy('position')->get();
        }

        return $templates;
    }
}
```

Wire it into `app/Observers/OrganizationObserver.php` beside the roles:

```php
public function __construct(
    protected SeedDefaultRoles $seedDefaultRoles,
    protected SeedDefaultIssueTypes $seedDefaultIssueTypes,
) {}

public function created(Organization $organization): void
{
    $this->seedDefaultRoles->handle($organization);
    $this->seedDefaultIssueTypes->handle($organization);
}
```

Add to `app/Models/Organization.php`:

```php
public function issueTypes(): HasMany
{
    return $this->hasMany(IssueType::class);
}
```

Add `IssueTypeSeeder::class` to `DatabaseSeeder::run()`'s first `$this->call([...])`, beside `RoleSeeder`.

- [ ] **Step 5: Run the type tests**

Run: `php artisan test --compact tests/Feature/Issues/IssueTypeTest.php`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/IssueType.php database/seeders app/Actions/Organizations/SeedDefaultIssueTypes.php app/Observers/OrganizationObserver.php app/Models/Organization.php tests/Feature/Issues/IssueTypeTest.php
git commit -m "Give each organization its own issue types"
```

- [ ] **Step 7: Write the failing test for issues**

`tests/Feature/Issues/IssueTest.php`:

```php
<?php

use App\Models\Client;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

function issueFor(Organization $organization, Project $project, ?Client $client = null, ?User $reporter = null): Issue
{
    return Issue::create([
        'organization_id' => $organization->id,
        'project_id' => $project->id,
        'client_id' => $client?->id,
        'issue_type_id' => IssueType::where('organization_id', $organization->id)->first()->id,
        'reported_by' => ($reporter ?? User::factory()->create())->id,
        'title' => 'The export button does nothing',
        'description' => 'Clicking export on the report page produces no file.',
    ]);
}

test('issue numbers are sequential within a project and independent across projects', function () {
    [$organization] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $first = Project::factory()->ownedBy($client)->create();
    $second = Project::factory()->ownedBy($client)->create();

    expect(issueFor($organization, $first, $client)->number)->toBe(1);
    expect(issueFor($organization, $first, $client)->number)->toBe(2);
    expect(issueFor($organization, $second, $client)->number)->toBe(1);
});

test('an issue is open until it is closed', function () {
    [$organization] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $issue = issueFor($organization, Project::factory()->ownedBy($client)->create(), $client);

    expect(Issue::open()->pluck('id'))->toContain($issue->id);

    $issue->close();

    expect($issue->fresh()->closed_at)->not->toBeNull();
    expect(Issue::open()->pluck('id'))->not->toContain($issue->id);
    expect(Issue::closed()->pluck('id'))->toContain($issue->id);

    $issue->reopen();

    expect($issue->fresh()->closed_at)->toBeNull();
});

test('staff may file against a team-owned project with no client', function () {
    [$organization, $owner] = organizationWith('owner');
    $team = Team::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($team)->create();

    $issue = issueFor($organization, $project, null, $owner);

    expect($issue->client_id)->toBeNull();
})->note('Internal work is for nobody, which is why client_id is nullable.');

test('an issue reported by a contact must carry that contact client', function () {
    [$organization] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $contact = contactFor($client, 'Lucy Alvarez');

    expect(fn () => issueFor($organization, $project, null, $contact))
        ->toThrow(RuntimeException::class);
});
```

- [ ] **Step 8: Run it and watch it fail**

Run: `php artisan test --compact tests/Feature/Issues/IssueTest.php`
Expected: FAIL — `Class "App\Models\Issue" not found`

- [ ] **Step 9: Write `Issue`, its factory, and the observer**

`app/Models/Issue.php`:

```php
<?php

namespace App\Models;

use App\Observers\IssueReporterObserver;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperIssue */

#[ObservedBy(IssueReporterObserver::class)]
#[UseFactory(IssueFactory::class)]
class Issue extends Model
{
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'closed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('organization');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(IssueType::class, 'issue_type_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('closed_at');
    }

    public function close(): void
    {
        $this->update(['closed_at' => now()]);
    }

    public function reopen(): void
    {
        $this->update(['closed_at' => null]);
    }

    protected static function boot(): void
    {
        parent::boot();

        /**
         * The number is allocated against a locked project row, so two issues
         * filed at the same moment cannot claim the same one. The unique index
         * is the backstop.
         */
        static::creating(function (Issue $issue) {
            if ($issue->number !== null) {
                return;
            }

            $issue->number = DB::transaction(function () use ($issue) {
                Project::whereKey($issue->project_id)->lockForUpdate()->firstOrFail();

                return (int) Issue::withTrashed()
                    ->where('project_id', $issue->project_id)
                    ->max('number') + 1;
            });
        });
    }

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }
}
```

`app/Observers/IssueReporterObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Issue;
use RuntimeException;

class IssueReporterObserver
{
    /**
     * A contact's issue must name the client they represent. Nothing else in the
     * app can show a contact's issue back to them without it.
     */
    public function saving(Issue $issue): void
    {
        $reporter = $issue->reporter;
        $organization = $issue->organization;

        if ($reporter === null || $organization === null) {
            return;
        }

        if (! $reporter->isClientContact($organization)) {
            return;
        }

        $contactClientId = $reporter->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->value('client_id');

        if ($issue->client_id !== $contactClientId) {
            throw new RuntimeException('A contact\'s issue must belong to the client they represent.');
        }
    }
}
```

`database/factories/IssueFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Issue> */
class IssueFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->paragraph(),
        ];
    }

    public function inProject(Project $project): static
    {
        return $this->state(fn () => [
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'issue_type_id' => IssueType::where('organization_id', $project->organization_id)->first()?->id
                ?? IssueType::factory()->create(['organization_id' => $project->organization_id])->id,
            'reported_by' => User::factory()->create()->id,
        ]);
    }
}
```

`database/factories/IssueTypeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\IssueType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IssueType> */
class IssueTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'position' => 0,
        ];
    }
}
```

Add to `app/Models/Project.php`:

```php
public function issues(): HasMany
{
    return $this->hasMany(Issue::class);
}
```

- [ ] **Step 10: Run the issue tests**

Run: `php artisan test --compact tests/Feature/Issues/IssueTest.php`
Expected: PASS (4 tests)

- [ ] **Step 11: Add the permissions**

In `database/seeders/RoleSeeder.php`, add `'issue:create'`, `'issue:update'`, `'issue:close'` to `$catalogue`, add all three to the `admin` and `member` bundles, and add `'issue:create'` alone to the `client` bundle.

Add to `tests/Feature/Organizations/DefaultRoleTemplatesTest.php`:

```php
test('a contact may file an issue and nothing else', function () {
    $organization = Organization::factory()->create();

    $granted = Role::where('organization_id', $organization->id)
        ->where('name', 'client')
        ->sole()
        ->permissions
        ->pluck('name');

    expect($granted->all())->toBe(['issue:create']);
})->note('Filing is how a contact uses Quill; everything else stays closed to them.');
```

- [ ] **Step 12: Run the full gate and commit**

```bash
php artisan migrate:fresh --seed --force
vendor/bin/pint --dirty --format agent
composer ci:check
git add -A
git commit -m "Add issues with per-project numbers"
```

---

### Task 2: The staff surface

**Files:**
- Create: `app/Http/Controllers/Projects/IssueController.php`, `app/Http/Controllers/Projects/IssueClosureController.php`
- Create: `app/Http/Requests/Projects/StoreIssueRequest.php`
- Create: `app/Policies/IssuePolicy.php`
- Create: `resources/js/pages/issues/Show.vue`, `resources/js/components/CreateIssueModal.vue`
- Modify: `routes/web.php`, `resources/js/pages/projects/Show.vue`, `app/Http/Controllers/Projects/ProjectController.php`, `resources/js/types/index.ts` (new `Issue` type)
- Test: `tests/Feature/Issues/IssuePageTest.php`

**Interfaces:**
- Consumes: `Issue`, `IssueType::active()`, `Project::issues()` from Task 1.
- Produces: routes `projects.issues.store`, `projects.issues.show`, `projects.issues.closure.store`, `projects.issues.closure.destroy`; `IssuePolicy::view`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Issues/IssuePageTest.php`:

```php
<?php

use App\Models\Client;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Organization;
use App\Models\Project;

test('the project page lists its open issues', function () {
    [$organization, $owner] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    $open = Issue::factory()->inProject($project)->create(['title' => 'Export is broken']);
    $closed = Issue::factory()->inProject($project)->create(['title' => 'Old thing', 'closed_at' => now()]);

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('issues.0.title', 'Export is broken')
            ->where('issues.0.number', $open->number)
            ->has('issues', 1)
            ->where('closedIssueCount', 1),
        );
});

test('a member holding issue:create can file one', function () {
    [$organization, $member] = organizationWith('member');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $type = IssueType::where('organization_id', $organization->id)->first();

    $this->actingAs($member)
        ->post(route('projects.issues.store', $project), [
            'issue_type_id' => $type->id,
            'title' => 'Export is broken',
            'description' => 'Clicking export produces no file.',
        ])
        ->assertRedirect();

    expect($project->issues()->sole()->title)->toBe('Export is broken');
});

test('a contact cannot reach a project issue page', function () {
    [$organization, $owner] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $issue = Issue::factory()->inProject($project)->create();
    $contact = contactFor($client, 'Lucy Alvarez');

    $contact->switchOrganization($organization);

    $this->actingAs($contact->refresh())
        ->get(route('projects.issues.show', [$project, $issue->number]))
        ->assertForbidden();
});

test('an issue in another organization is a not found', function () {
    [, $user] = organizationWith('owner');
    $theirs = Organization::factory()->create(['name' => '92 Labs']);
    $theirClient = Client::factory()->heldBy($theirs)->create();
    $theirProject = Project::factory()->ownedBy($theirClient)->create();
    $issue = Issue::factory()->inProject($theirProject)->create();

    $this->actingAs($user)
        ->get(route('projects.issues.show', [$theirProject, $issue->number]))
        ->assertNotFound();
});

test('closing and reopening an issue', function () {
    [$organization, $owner] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $issue = Issue::factory()->inProject($project)->create();

    $this->actingAs($owner)
        ->post(route('projects.issues.closure.store', [$project, $issue->number]))
        ->assertRedirect();

    expect($issue->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($owner)
        ->delete(route('projects.issues.closure.destroy', [$project, $issue->number]))
        ->assertRedirect();

    expect($issue->fresh()->closed_at)->toBeNull();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact tests/Feature/Issues/IssuePageTest.php`
Expected: FAIL — `Route [projects.issues.store] not defined`

- [ ] **Step 3: Write the policy, request, and controllers**

`app/Policies/IssuePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IssuePolicy
{
    public function view(User $user, Issue $issue): Response
    {
        $organization = $user->currentOrganization;

        $sameOrganization = $organization !== null
            && $issue->organization_id === $organization->id
            && $user->belongsToOrganization($organization);

        if (! $sameOrganization) {
            return Response::denyAsNotFound();
        }

        return $user->isClientContact($organization)
            ? Response::deny()
            : Response::allow();
    }
}
```

`app/Http/Requests/Projects/StoreIssueRequest.php`:

```php
<?php

namespace App\Http\Requests\Projects;

use App\Models\IssueType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'issue_type_id' => [
                'required',
                Rule::exists(IssueType::class, 'id')
                    ->where('organization_id', $this->user()->current_organization_id)
                    ->whereNull('archived_at'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'acceptance_criteria' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
```

`app/Http/Controllers/Projects/IssueController.php`:

```php
<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreIssueRequest;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function show(Request $request, Project $project, Issue $issue): Response
    {
        $issue->load(['type', 'client', 'reporter']);

        return Inertia::render('issues/Show', [
            'project' => ['name' => $project->name, 'slug' => $project->slug],
            'issue' => [
                'number' => $issue->number,
                'title' => $issue->title,
                'description' => $issue->description,
                'acceptanceCriteria' => $issue->acceptance_criteria,
                'type' => $issue->type->name,
                'clientName' => $issue->client?->name,
                'reporterName' => $issue->reporter?->name,
                'isOpen' => $issue->closed_at === null,
                'createdAt' => $issue->created_at?->toFormattedDateString(),
                'fromConversation' => $issue->conversation_id !== null,
            ],
        ]);
    }

    public function store(StoreIssueRequest $request, Project $project): RedirectResponse
    {
        $issue = Issue::create([
            ...$request->validated(),
            'organization_id' => $project->organization_id,
            'client_id' => $project->owner_type === \App\Models\Client::class ? $project->owner_id : null,
            'reported_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue filed.')]);

        return to_route('projects.issues.show', [$project->slug, $issue->number]);
    }
}
```

`app/Http/Controllers/Projects/IssueClosureController.php`:

```php
<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class IssueClosureController extends Controller
{
    public function store(Project $project, Issue $issue): RedirectResponse
    {
        $issue->close();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue closed.')]);

        return to_route('projects.issues.show', [$project->slug, $issue->number]);
    }

    public function destroy(Project $project, Issue $issue): RedirectResponse
    {
        $issue->reopen();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue reopened.')]);

        return to_route('projects.issues.show', [$project->slug, $issue->number]);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, inside the group that already holds `projects.show`:

```php
Route::post('projects/{project}/issues', [IssueController::class, 'store'])
    ->middleware(['can:view,project', 'can:issue:create'])
    ->name('projects.issues.store');

Route::get('projects/{project}/issues/{issue:number}', [IssueController::class, 'show'])
    ->middleware('can:view,issue')
    ->scopeBindings()
    ->name('projects.issues.show');

Route::post('projects/{project}/issues/{issue:number}/closure', [IssueClosureController::class, 'store'])
    ->middleware(['can:view,issue', 'can:issue:close'])
    ->scopeBindings()
    ->name('projects.issues.closure.store');

Route::delete('projects/{project}/issues/{issue:number}/closure', [IssueClosureController::class, 'destroy'])
    ->middleware(['can:view,issue', 'can:issue:close'])
    ->scopeBindings()
    ->name('projects.issues.closure.destroy');
```

- [ ] **Step 5: Pass the issues to the project page**

In `ProjectController::show`, add to the render array:

```php
'issues' => $project->issues()->open()->with('type')->orderByDesc('number')->get()
    ->map(fn (Issue $issue) => [
        'number' => $issue->number,
        'title' => $issue->title,
        'type' => $issue->type->name,
        'clientName' => $issue->client?->name,
    ])->values(),
'closedIssueCount' => $project->issues()->closed()->count(),
'issueTypes' => IssueType::active()
    ->where('organization_id', $project->organization_id)
    ->orderBy('position')
    ->get(['id', 'name']),
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact tests/Feature/Issues/IssuePageTest.php`
Expected: PASS (5 tests)

- [ ] **Step 7: Build the Vue surface**

Replace the `project-issues-placeholder` block in `resources/js/pages/projects/Show.vue` with a `Table` from `@/components/ui/table` (never hand-rolled markup — `.ai/rules/js.md`) whose rows link to `projects.issues.show`, an empty state when `issues.length === 0`, a line reading `{{ closedIssueCount }} closed` when that count is above zero, and a "File an issue" button rendering `CreateIssueModal` when `can('issue:create')` via `usePermissions()`. `CreateIssueModal` posts `projects.issues.store` with a `Select` of `issueTypes`, `TextInput` for the title, and `Textarea` for the description. `resources/js/pages/issues/Show.vue` renders the issue fields with a Close or Reopen button gated on `can('issue:close')`.

- [ ] **Step 8: Regenerate helpers, run the gate, commit**

```bash
php artisan wayfinder:generate --with-form
npm run format
vendor/bin/pint --dirty --format agent
composer ci:check
git add -A
git commit -m "Show and file issues on the project page"
```

---

### Task 3: `create_issue`, drafts, and confirmation

**Files:**
- Create: `database/migrations/2026_08_18_000003_create_issue_drafts_table.php`
- Create: `app/Models/IssueDraft.php`, `database/factories/IssueDraftFactory.php`
- Create: `app/Ai/Tools/CreateIssue.php`, `app/Ai/Tools/ListIssues.php`
- Create: `app/Http/Controllers/Assistant/IssueDraftController.php`
- Create: `resources/js/components/IssueDraftCard.vue`
- Modify: `app/Ai/AssistantToolbox.php`, `app/Http/Controllers/Assistant/AssistantController.php`, `routes/web.php`, `resources/js/pages/assistant/Show.vue`, `app/Ai/Agents/QuillAssistant.php`
- Test: `tests/Feature/Assistant/CreateIssueToolTest.php`, `tests/Feature/Issues/IssueDraftTest.php`

**Interfaces:**
- Consumes: `Issue`, `IssueType::active()` (Task 1); `AssistantTool` contract, `ScopedToCurrentOrganization` trait.
- Produces: `IssueDraft::promote(): Issue`; `IssueDraft::pending()` scope; routes `assistant.drafts.update`, `assistant.drafts.destroy`; tool name `create_issue`.

- [ ] **Step 1: Write the failing tool test**

`tests/Feature/Assistant/CreateIssueToolTest.php`:

```php
<?php

use App\Ai\Tools\CreateIssue;
use App\Models\Client;
use App\Models\Issue;
use App\Models\IssueDraft;
use App\Models\IssueType;
use App\Models\Project;

test('create_issue writes a draft and no issue', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);
    $client->update(['default_project_id' => $project->id]);

    $type = IssueType::where('organization_id', $this->organization->id)->first();

    $result = new CreateIssue($this->admin)->handle(toolRequest([
        'type' => $type->name,
        'title' => 'Export produces no file',
        'description' => 'Clicking export on the report page does nothing at all.',
        'acceptance_criteria' => 'Clicking export downloads a CSV.',
        'client' => 'Acme Title',
    ]));

    expect((string) $result)->toContain('Export produces no file');
    expect(IssueDraft::count())->toBe(1);
    expect(Issue::count())->toBe(0);
})->note('The draft is shown to the reporter before anything is filed.');

test('create_issue refuses an unknown type without inventing one', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $project = Project::factory()->ownedBy($client)->create();
    $client->update(['default_project_id' => $project->id]);

    $result = new CreateIssue($this->admin)->handle(toolRequest([
        'type' => 'Kerfuffle',
        'title' => 'Something',
        'description' => 'A description long enough to pass.',
        'client' => 'Acme Title',
    ]));

    expect((string) $result)->toContain('Kerfuffle');
    expect(IssueDraft::count())->toBe(0);
});

test('a promoted draft becomes an issue in the client default project', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $project = Project::factory()->ownedBy($client)->create();
    $client->update(['default_project_id' => $project->id]);

    $draft = IssueDraft::factory()->for($client)->create([
        'project_id' => $project->id,
        'reported_by' => $this->admin->id,
        'issue_type_id' => IssueType::where('organization_id', $this->organization->id)->first()->id,
    ]);

    $issue = $draft->promote();

    expect($issue->project_id)->toBe($project->id);
    expect($draft->fresh()->issue_id)->toBe($issue->id);
});

test('promoting a draft twice returns the same issue', function () {
    $client = Client::factory()->heldBy($this->organization)->create();
    $project = Project::factory()->ownedBy($client)->create();

    $draft = IssueDraft::factory()->for($client)->create([
        'project_id' => $project->id,
        'reported_by' => $this->admin->id,
        'issue_type_id' => IssueType::where('organization_id', $this->organization->id)->first()->id,
    ]);

    expect($draft->promote()->id)->toBe($draft->promote()->id);
    expect(Issue::count())->toBe(1);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact tests/Feature/Assistant/CreateIssueToolTest.php`
Expected: FAIL — `Class "App\Ai\Tools\CreateIssue" not found`

- [ ] **Step 3: Write the drafts migration and model**

`database/migrations/2026_08_18_000003_create_issue_drafts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A draft is pending while it has no issue and no discarded_at. Two
     * timestamps say what a status column would, without a vocabulary the code
     * has to agree on.
     */
    public function up(): void
    {
        Schema::create('issue_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('issue_type_id')->constrained('issue_types')->restrictOnDelete();
            $table->foreignUuid('reported_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->string('title');
            $table->text('description');
            $table->text('acceptance_criteria')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_drafts');
    }
};
```

`app/Models/IssueDraft.php`:

```php
<?php

namespace App\Models;

use Database\Factories\IssueDraftFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin IdeHelperIssueDraft */

#[UseFactory(IssueDraftFactory::class)]
class IssueDraft extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(IssueType::class, 'issue_type_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('issue_id')->whereNull('discarded_at');
    }

    /** Idempotent: a draft already promoted returns the issue it made. */
    public function promote(): Issue
    {
        if ($this->issue_id !== null) {
            return $this->issue;
        }

        $issue = Issue::create([
            'organization_id' => $this->organization_id,
            'project_id' => $this->project_id,
            'client_id' => $this->client_id,
            'issue_type_id' => $this->issue_type_id,
            'reported_by' => $this->reported_by,
            'conversation_id' => $this->conversation_id,
            'title' => $this->title,
            'description' => $this->description,
            'acceptance_criteria' => $this->acceptance_criteria,
        ]);

        $this->update(['issue_id' => $issue->id]);

        return $issue;
    }

    public function discard(): void
    {
        $this->update(['discarded_at' => now()]);
    }

    protected function casts(): array
    {
        return [
            'discarded_at' => 'datetime',
        ];
    }
}
```

`database/factories/IssueDraftFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\IssueDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IssueDraft> */
class IssueDraftFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->paragraph(),
        ];
    }

    public function for(Client $client): static
    {
        return $this->state(fn () => [
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
        ]);
    }
}
```

- [ ] **Step 4: Write `CreateIssue`**

`app/Ai/Tools/CreateIssue.php` — note the schema's allowed types come from the organization's rows, so a renamed type changes what the model may say with no deploy:

```php
<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\MatchesNames;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\Client;
use App\Models\IssueDraft;
use App\Models\IssueType;
use App\Models\Organization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateIssue implements AssistantTool
{
    use MatchesNames;
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'create_issue';
    }

    public function capability(): string
    {
        return 'Draft an issue for a client, for them to confirm before it is filed.';
    }

    public function description(): Stringable|string
    {
        return 'Draft an issue once you understand the request: what kind it is, a one-line title, a description someone could act on, and how we will know it is done. This does not file anything — the person confirms the draft first. Do not call it while anything important is still vague.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        if (! $this->user->can('issue:create')) {
            return $this->refused('file an issue', 'issue:create');
        }

        $client = $this->resolveClient($organization, $request['client'] ?? null);

        if (is_string($client)) {
            return $client;
        }

        if ($client->default_project_id === null) {
            return "There is no project set up for {$client->name} yet, so nothing was drafted. Tell the person you have passed this to the team, and that someone will follow up.";
        }

        $type = $this->resolveType($organization, (string) ($request['type'] ?? ''));

        if (is_string($type)) {
            return $type;
        }

        $title = trim((string) $request['title']);
        $description = trim((string) $request['description']);

        if ($title === '' || $description === '') {
            return 'A draft needs a title and a description. Keep asking until you have both.';
        }

        $draft = IssueDraft::create([
            'organization_id' => $organization->id,
            'project_id' => $client->default_project_id,
            'client_id' => $client->id,
            'issue_type_id' => $type->id,
            'reported_by' => $this->user->id,
            'conversation_id' => $this->user->assistantConversationId(),
            'title' => $title,
            'description' => $description,
            'acceptance_criteria' => trim((string) ($request['acceptance_criteria'] ?? '')) ?: null,
        ]);

        return "Drafted a {$type->name} for {$client->name}: \"{$draft->title}\". Ask them to confirm it, and say you will file it once they do.";
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('What kind of issue this is. One of: '.$this->typeNames()->join(', ').'.')
                ->required(),
            'title' => $schema->string()->description('One line naming the problem or the request.')->required(),
            'description' => $schema->string()->description('What someone needs to know to act on it, in the reporter\'s own terms.')->required(),
            'acceptance_criteria' => $schema->string()->description('How we will know it is done.'),
            'client' => $schema->string()->description('The client this is for. Omit it when the person reporting is a contact — their own client is used.'),
        ];
    }

    /** @return Collection<int, string> */
    protected function typeNames(): Collection
    {
        $organization = $this->user->currentOrganization;

        if ($organization === null) {
            return collect();
        }

        return IssueType::active()
            ->where('organization_id', $organization->id)
            ->orderBy('position')
            ->pluck('name');
    }

    protected function resolveType(Organization $organization, string $wanted): IssueType|string
    {
        $names = $this->typeNames();
        $matches = $this->matchingNames($names, $wanted, 'issue');

        if ($matches->count() === 1) {
            return IssueType::active()
                ->where('organization_id', $organization->id)
                ->where('name', $matches->sole())
                ->sole();
        }

        return "\"{$wanted}\" is not a kind of issue in {$organization->name}, so nothing was drafted. The kinds are: ".$names->join(', ').'.';
    }

    protected function resolveClient(Organization $organization, ?string $wanted): Client|string
    {
        $contactClientId = $this->user->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->value('client_id');

        if ($contactClientId !== null) {
            return Client::whereKey($contactClientId)->sole();
        }

        if (blank($wanted)) {
            return 'An issue belongs to a client. Ask which client this is for.';
        }

        $names = $organization->clients()->orderBy('name')->pluck('name');
        $matches = $this->matchingNames($names, (string) $wanted, 'client');

        if ($matches->count() === 1) {
            return $organization->clients()->where('name', $matches->sole())->sole();
        }

        if ($matches->count() > 1) {
            return "More than one client matches \"{$wanted}\": ".$matches->join(', ').'. Nothing was drafted — ask which one.';
        }

        return "There is no client called {$wanted} in {$organization->name}, so nothing was drafted.";
    }
}
```

Add `assistantConversationId(): ?string` to `app/Concerns/HasAssistantConversation.php`, returning the id of the conversation the transcript already resumes.

- [ ] **Step 5: Run the tool tests**

Run: `php artisan test --compact tests/Feature/Assistant/CreateIssueToolTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Write `ListIssues` and grant both tools**

`ListIssues` mirrors `ListProjects`: name `list_issues`, capability `'List the issues in a project.'`, a `project` string in its schema, and a handler returning `#{number} {title} ({type}) — open|closed` lines from `$organization->projects()` scoped issues. In `AssistantToolbox::for()`, append `new ListIssues($user)` to the read tools and `new CreateIssue($user)` when `$permitted('issue:create')`.

- [ ] **Step 7: Write the confirmation endpoint and card**

`app/Http/Controllers/Assistant/IssueDraftController.php` — `update()` promotes (`can:confirm,draft` via a policy asserting the draft's reporter is the caller and it is pending) and `destroy()` discards. Routes:

```php
Route::put('assistant/drafts/{draft}', [IssueDraftController::class, 'update'])
    ->middleware('can:confirm,draft')
    ->name('assistant.drafts.update');

Route::delete('assistant/drafts/{draft}', [IssueDraftController::class, 'destroy'])
    ->middleware('can:confirm,draft')
    ->name('assistant.drafts.destroy');
```

`AssistantController::show` passes `pendingDraft` (the caller's `IssueDraft::pending()->latest()->first()`, with type and client names) and the page renders `IssueDraftCard.vue` beneath the transcript: title, type, description, acceptance criteria, a Confirm button putting to `assistant.drafts.update`, and a "Not quite" button deleting it.

- [ ] **Step 8: Run the gate and commit**

```bash
php artisan wayfinder:generate --with-form
npm run format
vendor/bin/pint --dirty --format agent
composer ci:check
git add -A
git commit -m "Draft issues from the assistant and confirm them"
```

---

### Task 4: Contact access and the grooming rubric

**Files:**
- Create: `app/Ai/Prompts/GroomingRubric.php`
- Modify: `routes/web.php` (drop `DenyClientContacts` from the assistant routes), `app/Ai/AssistantToolbox.php`, `app/Ai/Agents/QuillAssistant.php`
- Test: `tests/Feature/Assistant/ContactGroomingTest.php`

**Interfaces:**
- Consumes: `CreateIssue` (Task 3), `AssistantToolbox::for()`.
- Produces: `GroomingRubric::for(User): string`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Assistant/ContactGroomingTest.php`:

```php
<?php

use App\Ai\AssistantToolbox;
use App\Ai\Agents\QuillAssistant;
use App\Models\Client;
use App\Models\Project;

test('a contact is granted create_issue and nothing else', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $project = Project::factory()->ownedBy($client)->create();
    $client->update(['default_project_id' => $project->id]);

    $contact = contactFor($client, 'Lucy Alvarez');
    $contact->switchOrganization($this->organization);

    $names = collect(app(AssistantToolbox::class)->for($contact->refresh()))
        ->map(fn ($tool) => $tool->name());

    expect($names->all())->toBe(['create_issue']);
})->note('A contact must not learn the organization internal structure.');

test('a contact can reach the assistant', function () {
    $client = Client::factory()->heldBy($this->organization)->create();
    $contact = contactFor($client, 'Lucy Alvarez');
    $contact->switchOrganization($this->organization);

    $this->actingAs($contact->refresh())
        ->get(route('assistant'))
        ->assertOk();
});

test('the rubric tells the contact which kinds of issue exist in their organization', function () {
    $client = Client::factory()->heldBy($this->organization)->create();
    $contact = contactFor($client, 'Lucy Alvarez');
    $contact->switchOrganization($this->organization);

    $renamed = $this->organization->issueTypes()->first();
    $renamed->update(['name' => 'Defect']);

    $instructions = new QuillAssistant($contact->refresh())->instructions();

    expect($instructions)->toContain('Defect');
})->note('Types are that organization data, so the prompt is built from rows.');

test('staff keep their own tools and the contact rubric is not used on them', function () {
    $names = collect(app(AssistantToolbox::class)->for($this->admin))
        ->map(fn ($tool) => $tool->name());

    expect($names)->toContain('list_clients', 'create_issue');

    expect(new QuillAssistant($this->admin)->instructions())
        ->toContain('You are the Quill assistant');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact tests/Feature/Assistant/ContactGroomingTest.php`
Expected: FAIL — the toolbox returns the staff tools for a contact

- [ ] **Step 3: Write the rubric**

`app/Ai/Prompts/GroomingRubric.php` — one file because it is tuned most. `for(User $user): string` returns a prompt that names the contact and their client, lists the organization's active type names, and instructs the model to: classify the request as one of those kinds; press on vague answers rather than collecting them ("it's broken" earns "what did you click, what did you expect, what happened instead"); call `create_issue` only when the picture is complete; never promise a date, a price, or a person; decline anything off-topic in one sentence and return to the request; and never mention projects, teams, or other clients.

- [ ] **Step 4: Branch the toolbox and the instructions**

In `AssistantToolbox::for()`, return early for a contact:

```php
if ($organization !== null && $user->isClientContact($organization)) {
    return $user->can('issue:create') ? [new CreateIssue($user)] : [];
}
```

In `QuillAssistant::instructions()`, return `GroomingRubric::for($this->user)` when the user is a contact of their current organization; otherwise the existing prompt with its "Issues do not exist in Quill yet" paragraph replaced by what the issue tools can now do.

- [ ] **Step 5: Open the assistant to contacts**

Remove `DenyClientContacts::class` from the assistant route group in `routes/web.php`, leaving it on `projects.*`. Update `tests/Feature/Assistant/AssistantChatTest.php`'s `a client contact cannot reach the assistant` to assert the opposite: a contact gets `200` and is granted one tool. Delete `app/Http/Middleware/DenyClientContacts.php` only if no route still uses it — `projects.index` and `projects.show` do, so it stays.

- [ ] **Step 6: Run the tests and commit**

```bash
php artisan test --compact tests/Feature/Assistant
vendor/bin/pint --dirty --format agent
composer ci:check
git add -A
git commit -m "Let client contacts groom an issue with the assistant"
```

---

### Task 5: Bounds and escalation

**Files:**
- Create: `app/Notifications/Issues/GroomingNeedsHuman.php`
- Modify: `app/Http/Controllers/Assistant/AssistantController.php`, `routes/web.php`, `config/quill.php` (create if absent, holding `assistant.turn_cap`)
- Test: `tests/Feature/Assistant/GroomingBoundsTest.php`

**Interfaces:**
- Consumes: the assistant message route (Task 3/4), `IssueDraft::pending()`.
- Produces: `GroomingNeedsHuman` notification; `config('quill.assistant.turn_cap')`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Assistant/GroomingBoundsTest.php`:

```php
<?php

use App\Ai\Agents\QuillAssistant;
use App\Models\Client;
use App\Models\Project;
use App\Notifications\Issues\GroomingNeedsHuman;
use Illuminate\Support\Facades\Notification;

test('the turn cap stops the conversation and puts it on a human desk', function () {
    config(['quill.assistant.turn_cap' => 3]);
    Notification::fake();
    QuillAssistant::fake(fn (string $prompt) => 'Tell me more.');

    $client = Client::factory()->heldBy($this->organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $client->update(['default_project_id' => $project->id]);

    $contact = contactFor($client, 'Lucy Alvarez');
    $contact->switchOrganization($this->organization);
    $contact = $contact->refresh();

    foreach (range(1, 3) as $turn) {
        $this->sendToAssistant($contact, "Turn {$turn}");
    }

    $response = $this->actingAs($contact)
        ->post(route('assistant.messages.store'), ['message' => 'Turn 4']);

    $response->assertOk();

    expect(assistantDeltas($response->streamedContent()))->toBe('');

    Notification::assertSentTo($this->organization->owner, GroomingNeedsHuman::class);
})->note('A leaked session must not be able to run up a token bill.');

test('the escalation is recorded in the organization history', function () {
    config(['quill.assistant.turn_cap' => 1]);
    Notification::fake();
    QuillAssistant::fake(fn (string $prompt) => 'Tell me more.');

    $client = Client::factory()->heldBy($this->organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $client->update(['default_project_id' => $project->id]);

    $contact = contactFor($client, 'Lucy Alvarez');
    $contact->switchOrganization($this->organization);
    $contact = $contact->refresh();

    $this->sendToAssistant($contact, 'One');
    $this->actingAs($contact)->post(route('assistant.messages.store'), ['message' => 'Two'])
        ->streamedContent();

    expect(App\Models\Activity::forOrganization($this->organization)->get()
        ->pluck('description'))
        ->toContain('Grooming conversation needs a human');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact tests/Feature/Assistant/GroomingBoundsTest.php`
Expected: FAIL — `Class "App\Notifications\Issues\GroomingNeedsHuman" not found`

- [ ] **Step 3: Write the notification**

`app/Notifications/Issues/GroomingNeedsHuman.php` implements `ShouldQueue`, uses `Queueable`, `via()` returns `['database', 'broadcast']`, and `toArray()` matches the shape `HasNotificationFeed` reads (`title`, `organization_name`, plus `client_name` and `contact_name`), so an arriving notification looks like a reloaded one. `toBroadcast()` adds `created_at_diff => __('just now')`.

- [ ] **Step 4: Enforce the cap in the controller**

In `AssistantController::store`, before prompting: count the caller's persisted user messages in their conversation. When that count is at or over `config('quill.assistant.turn_cap')` and no pending draft exists, notify every member holding `activity:view` (via `canInOrganization`), write an activity entry described `'Grooming conversation needs a human'`, and return a stream carrying one sentence telling the person a human will pick it up — without prompting the agent.

- [ ] **Step 5: Rate limit the route**

Add `->middleware('throttle:20,1')` to `assistant.messages.store` in `routes/web.php`.

- [ ] **Step 6: Run the tests, the full gate, and MySQL**

```bash
php artisan test --compact tests/Feature/Assistant/GroomingBoundsTest.php
php artisan migrate:fresh --seed --force
vendor/bin/pint --dirty --format agent
composer ci:check
git add -A
git commit -m "Cap grooming turns and escalate to a human"
```

---

## Self-Review

**Spec coverage:** one agent / two grants → Task 4. Schema and per-organization types → Task 1. Keys and `closed_at` → Task 1. Grooming flow and draft-then-confirm → Task 3. Derived state → Task 3 (`pending()` scope, no status column). Permissions → Task 1. No destination → Task 3 (`default_project_id` null branch). Bounds and escalation → Task 5. Staff surface → Task 2. Every testing bullet in the spec maps to a test in Tasks 1–5.

**Type consistency:** `IssueDraft::promote()`, `IssueDraft::discard()`, `IssueDraft::pending()`, `Issue::close()`, `Issue::reopen()`, `Issue::open()`, `Issue::closed()`, `IssueType::active()`, `SeedDefaultIssueTypes::handle()`, `GroomingRubric::for()` are each defined once and referenced by those names throughout.

**Known gap, deliberately out of scope:** no screen edits issue types. The table and the copy exist; the editor belongs with the roles settings screen, which has the same shape.
