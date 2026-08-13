<?php

use App\Enums\OrganizationRole;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;

function dashboardFor(App\Models\User $user): string
{
    return route('dashboard', ['current_team' => $user->currentTeam?->slug]);
}

/**
 * The fixture's own creation is logged, so clear those rows before asserting.
 */
function forgetSetupActivity(): void
{
    Activity::query()->delete();
}

test('creating and renaming are recorded against the organization', function () {
    [$organization, $admin] = organizationWith(OrganizationRole::Admin);

    $this->actingAs($admin);

    forgetSetupActivity();

    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);
    $client->update(['name' => 'Acme Title Co']);

    $entries = Activity::forOrganization($organization)->latest()->get();

    expect($entries)->toHaveCount(2);
    expect($entries->first()->event)->toBe('updated');
    expect($entries->first()->causer->is($admin))->toBeTrue();
    expect($entries->first()->attribute_changes['old']['name'])->toBe('Acme Title');
});

test('activity from another organization is never included', function () {
    [$mine, $admin] = organizationWith(OrganizationRole::Admin);
    $theirs = Organization::factory()->create(['name' => '92 Labs']);

    forgetSetupActivity();

    Client::factory()->heldBy($mine)->create(['name' => 'Mine']);
    Client::factory()->heldBy($theirs)->create(['name' => 'Theirs']);

    $summaries = Activity::forOrganization($mine)->get()
        ->map(fn (Activity $activity) => $activity->subject?->name);

    expect($summaries)->toContain('Mine');
    expect($summaries)->not->toContain('Theirs');
})->note('activity_log has no notion of a tenant, so organization_id is the whole boundary.');

test('teams, clients, and invitations all land in the same history', function () {
    [$organization, $admin] = organizationWith(OrganizationRole::Admin);

    $this->actingAs($admin);

    forgetSetupActivity();

    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);
    Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);
    App\Models\OrganizationInvitation::factory()->forClient($client)->create([
        'email' => 'lucy@acme.test',
        'invited_by' => $admin->id,
    ]);

    $subjects = Activity::forOrganization($organization)->get()
        ->map(fn (Activity $activity) => class_basename((string) $activity->subject_type))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($subjects)->toBe(['Client', 'OrganizationInvitation', 'Team']);

});

test('the organization id is stamped as the row is written', function () {
    [$organization, $admin] = organizationWith(OrganizationRole::Admin);

    forgetSetupActivity();

    Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    expect(Activity::sole()->organization_id)->toBe($organization->id);
    expect(Activity::sole()->getKey())->toBeString();
})->note('The published migration typed the keys as bigints, which UUIDs truncate to 0 on MySQL.');

test('an admin sees the paginated history on the dashboard', function () {
    [$organization, $admin] = organizationWith(OrganizationRole::Admin);

    $this->actingAs($admin);

    forgetSetupActivity();

    foreach (range(1, 20) as $index) {
        Client::factory()->heldBy($organization)->create(['name' => "Client {$index}"]);
    }

    $this->get(dashboardFor($admin))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activity.data', 15)
            ->where('activity.total', 20)
            ->where('activity.current_page', 1)
            ->where('activity.last_page', 2),
        );
});

test('a later page returns the rest', function () {
    [$organization, $admin] = organizationWith(OrganizationRole::Admin);

    $this->actingAs($admin);

    forgetSetupActivity();

    foreach (range(1, 20) as $index) {
        Client::factory()->heldBy($organization)->create(['name' => "Client {$index}"]);
    }

    $this->get(dashboardFor($admin).'?activity=2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activity.data', 5)
            ->where('activity.current_page', 2),
        );
});

test('the summary reads as a sentence rather than an event name', function () {
    [$organization, $admin] = organizationWith(OrganizationRole::Admin);

    $this->actingAs($admin);

    forgetSetupActivity();

    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);
    $client->update(['name' => 'Acme Title Co']);

    $this->get(dashboardFor($admin))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activity.data.0.summary', 'Renamed client Acme Title to Acme Title Co')
            ->where('activity.data.0.causerName', $admin->name)
            ->where('activity.data.1.summary', 'Created client Acme Title'),
        );
});

test('a member is not given the history at all', function () {
    [$organization, $member] = organizationWith(OrganizationRole::Member);

    forgetSetupActivity();

    Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $this->actingAs($member)
        ->get(dashboardFor($member))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('activity', null));
})->note('The permission gates the prop, so the data never reaches the browser.');

test('an owner sees the history', function () {
    [$organization, $owner] = organizationWith(OrganizationRole::Owner);

    forgetSetupActivity();

    Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $this->actingAs($owner)
        ->get(dashboardFor($owner))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activity.data', 1));
});

test('deleting an organization is logged and the history survives it', function () {
    [$organization, $admin] = organizationWith(OrganizationRole::Admin);

    $this->actingAs($admin);

    forgetSetupActivity();

    $organizationId = $organization->id;

    $organization->forceDelete();

    $entries = Activity::where('organization_id', $organizationId)->get();

    expect($entries)->not->toBeEmpty();
    expect($entries->pluck('event'))->toContain('deleted');
})->note('The log row is written after the organization is gone, so organization_id cannot be a foreign key.');

test('membership and invitation entries read as sentences too', function () {
    [$organization, $admin] = organizationWith(OrganizationRole::Admin);

    $this->actingAs($admin);

    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    forgetSetupActivity();

    contactFor($client, 'Lucy Alvarez');

    App\Models\OrganizationInvitation::factory()->forClient($client)->create([
        'email' => 'nina@acme.test',
        'invited_by' => $admin->id,
    ]);

    $summaries = collect(
        $this->get(dashboardFor($admin))->viewData('page')['props']['activity']['data'] ?? []
    )->pluck('summary');

    expect($summaries)->toContain('Invited nina@acme.test');
    expect($summaries)->toContain('Lucy Alvarez joined the organization');
})->note('"Created organization membership" against a UUID tells an admin nothing.');

test('personal teams are not organization history', function () {
    [$organization, $admin] = organizationWith(OrganizationRole::Admin);

    forgetSetupActivity();

    Team::factory()->create(['name' => "Someone's Team", 'is_personal' => true]);
    Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);

    expect(Activity::count())->toBe(1);
    expect(Activity::sole()->subject->name)->toBe('Delivery');
})->note('A personal team is a private workspace and carries no organization to file it under.');

test('seeded history is attributed to a person, not the system', function () {
    $this->seed();

    expect(Activity::whereNull('causer_id')->count())->toBe(0);
    expect(Activity::whereNull('organization_id')->count())->toBe(0);

    $notaryDash = Organization::where('name', 'NotaryDash')->sole();

    expect(Activity::forOrganization($notaryDash)->with('causer')->get()->pluck('causer.name')->unique()->all())
        ->toBe(['Jen']);
})->note('An activity table full of "System" is not a used app.');
