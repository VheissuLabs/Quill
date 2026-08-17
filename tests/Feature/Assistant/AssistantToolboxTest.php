<?php

use App\Ai\AssistantToolbox;
use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\ListCapabilities;
use App\Models\User;

function grantedTo(User $user): array
{
    return collect(app(AssistantToolbox::class)->for($user))
        ->map(fn (AssistantTool $tool) => $tool->name())
        ->all();
}

test('an admin is granted the write tools', function () {
    expect(grantedTo($this->admin))->toBe([
        'describe_organization',
        'list_clients',
        'list_teams',
        'list_contacts',
        'list_projects',
        'create_client',
        'rename_client',
        'create_team',
        'rename_team',
        'create_project',
        'rename_project',
        'create_contact',
        'list_capabilities',
    ]);
});

test('an owner is granted everything an admin is', function () {
    $owner = memberOf($this->organization, 'owner');

    expect(grantedTo($owner))->toBe(grantedTo($this->admin));
});

test('a member is granted no write tools at all', function () {
    $member = memberOf($this->organization, 'member');

    expect(grantedTo($member))->toBe([
        'describe_organization',
        'list_clients',
        'list_teams',
        'list_contacts',
        'list_projects',
        'list_capabilities',
    ]);
})->note('Withholding the grant stops the model offering something it would then be refused.');

test('a user with no organization is granted no write tools', function () {
    $stranger = User::factory()->create(['current_organization_id' => null]);

    expect(grantedTo($stranger))->not->toContain('create_client');
});

test('every granted tool describes itself in plain language', function () {
    foreach (app(AssistantToolbox::class)->for($this->admin) as $tool) {
        expect($tool->capability())
            ->toBeString()
            ->not->toBeEmpty();

        expect($tool->capability())->not->toContain('Only call this');
    }
})->note('capability() is for the person using the app; description() is for the model.');

test('list_capabilities reports what this user can actually do', function () {
    $result = new ListCapabilities($this->admin, app(AssistantToolbox::class)->for($this->admin))
        ->handle(toolRequest());

    expect($result)
        ->toContain('NotaryDash')
        ->toContain('as Admin')
        ->toContain('Create a new client')
        ->toContain('Invite someone to be a contact')
        ->toContain('Rename one of your clients')
        ->toContain('cannot delete anything');
});

test('list_capabilities offers a member nothing it cannot do', function () {
    $member = memberOf($this->organization, 'member');

    $result = new ListCapabilities($member, app(AssistantToolbox::class)->for($member))
        ->handle(toolRequest());

    expect($result)
        ->toContain('as Member')
        ->toContain('List your clients')
        ->not->toContain('Create a new client')
        ->not->toContain('Create a new team')
        ->not->toContain('Rename one of your')
        ->not->toContain('Invite someone');
});

test('list_capabilities takes no arguments', function () {
    expect(new ListCapabilities($this->admin)->schema(new Illuminate\JsonSchema\JsonSchemaTypeFactory))->toBe([]);
});
