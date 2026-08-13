<?php

use App\Ai\AssistantToolbox;
use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\CreateProject;
use App\Ai\Tools\ListProjects;
use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

test('list_projects says who owns each project', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $team = Team::factory()->heldBy($this->organization)->create(['name' => 'Delivery']);

    Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);
    Project::factory()->ownedBy($team)->create(['name' => 'Harbor Rebuild']);

    expect(new ListProjects($this->admin)->handle(toolRequest()))
        ->toContain('Acme Website (owned by the client Acme Title)')
        ->toContain('Harbor Rebuild (owned by the team Delivery)');
});

test('an organization with no projects says so', function () {
    expect(new ListProjects($this->admin)->handle(toolRequest()))
        ->toBe('NotaryDash has no projects yet.');
});

test('list_projects never returns another organization projects', function () {
    $theirs = Organization::factory()->create(['name' => '92 Labs']);

    Project::factory()->ownedBy(Client::factory()->heldBy($theirs)->create())->create(['name' => 'Secret Project']);

    expect(new ListProjects($this->admin)->handle(toolRequest()))
        ->not->toContain('Secret Project');
});

test('a project can be created for a client', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new CreateProject($this->admin)->handle(toolRequest([
        'name' => 'Acme Website',
        'client' => 'Acme Title',
    ]));

    expect($result)->toContain('Created the project Acme Website in NotaryDash, owned by the client Acme Title');
    expect($this->organization->projects()->sole()->owner)->toBeInstanceOf(Client::class);
});

test('a project can be created for a team', function () {
    Team::factory()->heldBy($this->organization)->create(['name' => 'Delivery']);

    $result = new CreateProject($this->admin)->handle(toolRequest([
        'name' => 'Harbor Rebuild',
        'team' => 'Delivery',
    ]));

    expect($result)->toContain('owned by the team Delivery');
    expect($this->organization->projects()->sole()->owner)->toBeInstanceOf(Team::class);
});

test('a project with no owner asks who owns it', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new CreateProject($this->admin)->handle(toolRequest(['name' => 'Orphan']));

    expect($result)->toContain('Every project has an owner');
    expect($this->organization->projects()->count())->toBe(0);
})->note('issues.project_id is not nullable, so a project with no owner has nowhere to attribute work.');

test('naming both a client and a team asks which', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    Team::factory()->heldBy($this->organization)->create(['name' => 'Delivery']);

    $result = new CreateProject($this->admin)->handle(toolRequest([
        'name' => 'Confused',
        'client' => 'Acme Title',
        'team' => 'Delivery',
    ]));

    expect($result)->toContain('not both');
    expect($this->organization->projects()->count())->toBe(0);
});

test('an existing project is returned rather than duplicated', function (string $rephrased) {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    $result = new CreateProject($this->admin)->handle(toolRequest([
        'name' => $rephrased,
        'client' => 'Acme Title',
    ]));

    expect($result)->toContain('already has a project called Acme Website');
    expect($this->organization->projects()->count())->toBe(1);
})->with(['Acme Website', 'acme website', 'Acme Website.']);

test('an owner that does not exist creates nothing and lists the real ones', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new CreateProject($this->admin)->handle(toolRequest([
        'name' => 'Wayne Portal',
        'client' => 'Wayne Enterprises',
    ]));

    expect($result)
        ->toContain('There is no client called Wayne Enterprises')
        ->toContain('Acme Title');

    expect($this->organization->projects()->count())->toBe(0);
});

test('a client in another organization cannot own the project', function () {
    $theirs = Organization::factory()->create(['name' => '92 Labs']);

    Client::factory()->heldBy($theirs)->create(['name' => 'Their Client']);

    $result = new CreateProject($this->admin)->handle(toolRequest([
        'name' => 'Sneaky',
        'client' => 'Their Client',
    ]));

    expect($result)->toContain('There is no client called Their Client');
    expect(Project::count())->toBe(0);
});

test('a member is refused and nothing is created', function () {
    $member = memberOf($this->organization, OrganizationRole::Member);

    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new CreateProject($member)->handle(toolRequest([
        'name' => 'Acme Website',
        'client' => 'Acme Title',
    ]));

    expect($result)->toContain('does not have permission');
    expect(Project::count())->toBe(0);
});

test('a member is not granted the create tool at all', function () {
    $member = memberOf($this->organization, OrganizationRole::Member);

    $granted = collect(app(AssistantToolbox::class)->for($member))
        ->map(fn (AssistantTool $tool) => $tool->name());

    expect($granted)->toContain('list_projects');
    expect($granted)->not->toContain('create_project');
});

test('a blank name asks rather than creating', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new CreateProject($this->admin)->handle(toolRequest([
        'name' => '  ',
        'client' => 'Acme Title',
    ]));

    expect($result)->toContain('A project needs a name');
});

test('a removed member cannot create a project', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $this->organization->members()->detach($this->admin);

    $result = new CreateProject($this->admin->refresh())->handle(toolRequest([
        'name' => 'Acme Website',
        'client' => 'Acme Title',
    ]));

    expect($result)->toContain('not currently working in any organization');
    expect(Project::count())->toBe(0);
});

test('the project tools declare no organization argument', function () {
    $schema = new Illuminate\JsonSchema\JsonSchemaTypeFactory;

    expect(new ListProjects($this->admin)->schema($schema))->toBe([]);
    expect(array_keys(new CreateProject($this->admin)->schema($schema)))->toBe(['name', 'client', 'team']);
});

test('a user with no organization sees nothing and creates nothing', function () {
    $stranger = User::factory()->create(['current_organization_id' => null]);

    expect(new ListProjects($stranger)->handle(toolRequest()))
        ->toContain('not currently working in any organization');

    expect(new CreateProject($stranger)->handle(toolRequest(['name' => 'X', 'client' => 'Y'])))
        ->toContain('not currently working in any organization');
});
