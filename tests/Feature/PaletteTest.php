<?php

use App\Enums\AddressType;
use App\Enums\ProjectVisibility;
use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Comment;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\Feedback;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;

function palette(Organization $organization, string $term): string
{
    return route('palette.search', ['current_organization' => $organization, 'q' => $term]);
}

test('a member searches every record type at once', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create(['name' => 'Orbit web']);
    Issue::factory()->for($project)->create(['title' => 'Orbit drifts on resize', 'number' => 12]);
    Feedback::factory()->for($project)->create(['title' => 'Orbit dark mode']);
    Team::factory()->for($organization)->create(['name' => 'Orbit crew']);
    $client = Client::factory()->for($organization)->create(['name' => 'Orbit Industries']);
    ClientContact::factory()->for($client)->create(['name' => 'Orbit Olsen']);
    Sprint::factory()->for(Team::factory()->for($organization), 'owner')->create(['name' => 'Orbit sprint 3']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'orbit'));

    $response->assertOk();

    expect(collect($response->json())->pluck('type')->unique()->sort()->values()->all())
        ->toBe(['client', 'contact', 'feedback', 'issue', 'project', 'sprint', 'team']);
});

test('a contact is found by name or email and opens their contact page', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $client = Client::factory()->for($organization)->create(['name' => 'Real Time Feedback']);
    $kfir = ClientContact::factory()->for($client)->create(['name' => 'Kfir Alfia', 'email' => 'kfir@rtf.test']);
    $deleted = Client::factory()->for($organization)->create();
    ClientContact::factory()->for($deleted)->create(['name' => 'Kfir Gone']);
    $deleted->delete();
    ClientContact::factory()->create(['name' => 'Kfir Elsewhere']);

    foreach (['kfi', 'rtf.test'] as $term) {
        expect($this->actingAs($member)->getJson(palette($organization, $term))->json())->toBe([[
            'type' => 'contact',
            'title' => 'Kfir Alfia',
            'subtitle' => 'Real Time Feedback',
            'url' => route('clients.contacts.show', ['current_organization' => $organization, 'client' => $client, 'contact' => $kfir]),
        ]]);
    }
});

test('an issue is found by its number as well as its title', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create(['name' => 'Ranking']);
    Issue::factory()->for($project)->create(['title' => 'Nothing alike', 'number' => 142]);

    $response = $this->actingAs($member)->getJson(palette($organization, '142'));

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.type'))->toBe('issue')
        ->and($response->json('0.title'))->toContain('Nothing alike');
});

test('projects the member cannot see are left out, along with their issues and feedback', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');

    $hidden = Project::factory()->for($organization)->create([
        'name' => 'Secret ledger',
        'visibility' => ProjectVisibility::Private,
    ]);
    $hidden->teams()->attach(Team::factory()->for($organization)->create());
    Issue::factory()->for($hidden)->create(['title' => 'Secret regression']);
    Feedback::factory()->for($hidden)->create(['title' => 'Secret export']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'secret'));

    $response->assertOk();
    expect($response->json())->toBeEmpty();
});

test('a disabled capability removes its records from the results', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $organization->capabilitySettings()->create([
        'module' => 'development',
        'capability' => 'issues',
        'enabled' => false,
    ]);
    $project = Project::factory()->for($organization)->create(['name' => 'Beacon']);
    Issue::factory()->for($project)->create(['title' => 'Beacon flickers']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'beacon'));

    expect(collect($response->json())->pluck('type')->all())->not->toContain('issue');
});

test('records belonging to another organization never appear', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    Project::factory()->create(['name' => 'Foreign atlas']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'atlas'));

    expect($response->json())->toBeEmpty();
});

test('a non member cannot search an organization', function () {
    $outsider = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($outsider)->getJson(palette($organization, 'anything'))->assertForbidden();
});

test('a guest cannot search at all', function () {
    $organization = Organization::factory()->create();

    $this->getJson(palette($organization, 'anything'))->assertUnauthorized();
});

test('a term shorter than two characters returns nothing', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    Project::factory()->for($organization)->create(['name' => 'Atlas']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'a'));

    $response->assertOk();
    expect($response->json())->toBeEmpty();
});

test('each record type is capped so one type cannot flood the palette', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create(['name' => 'Cap test']);
    Issue::factory()->for($project)->count(12)->sequence(fn ($sequence) => [
        'title' => "Cap issue {$sequence->index}",
        'number' => $sequence->index + 1,
    ])->create();

    $response = $this->actingAs($member)->getJson(palette($organization, 'cap'));

    expect(collect($response->json())->where('type', 'issue'))->toHaveCount(5);
});

test('the options endpoint carries what the create modals need', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    Project::factory()->for($organization)->create(['name' => 'Atlas']);

    $response = $this->actingAs($member)
        ->getJson(route('palette.options', ['current_organization' => $organization]));

    $response->assertOk()
        ->assertJsonStructure([
            'issue' => ['projects', 'teams', 'sprints', 'members', 'statuses', 'priorities'],
            'visibilities',
        ]);
});

test('the options endpoint gives an owner what every create form needs', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $client = Client::factory()->for($organization)->create(['name' => 'Acme']);
    Project::factory()->for($organization)->public()->create(['name' => 'Portal', 'client_id' => $client->id]);

    $response = $this->actingAs($owner)
        ->getJson(route('palette.options', ['current_organization' => $organization]));

    $response->assertOk()
        ->assertJsonPath('clients.0.slug', $client->slug)
        ->assertJsonPath('invoiceProjects.0.billToClientId', $client->id)
        ->assertJsonPath('feedbackProjects.0.name', 'Portal')
        ->assertJsonPath('sprintBoard', $organization->name);

    expect($response->json('roles'))->not->toBeEmpty()
        ->and($response->json('permissions'))->not->toBeEmpty()
        ->and($response->json('workTypeColors'))->toContain('gray');
});

test('the options endpoint withholds what a member cannot create', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    Client::factory()->for($organization)->create();

    $response = $this->actingAs($member)
        ->getJson(route('palette.options', ['current_organization' => $organization]));

    $response->assertOk()
        ->assertJsonPath('clients', [])
        ->assertJsonPath('invoiceProjects', [])
        ->assertJsonPath('roles', [])
        ->assertJsonPath('permissions', [])
        ->assertJsonPath('workTypeColors', []);
});

test('the options endpoint withholds issue options when the capability is off', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $organization->capabilitySettings()->create([
        'module' => 'development',
        'capability' => 'issues',
        'enabled' => false,
    ]);

    $response = $this->actingAs($member)
        ->getJson(route('palette.options', ['current_organization' => $organization]));

    $response->assertOk();
    expect($response->json('issue'))->toBeNull();
});

test('a non member cannot read palette options', function () {
    $outsider = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($outsider)
        ->getJson(route('palette.options', ['current_organization' => $organization]))
        ->assertForbidden();
});

test('a title that opens with the term outranks one that merely contains it', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create(['name' => 'Ranking Co']);
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create(['name' => 'Ranking']);

    Issue::factory()->for($project)->create(['title' => 'Aut fugit esse molestiae', 'number' => 1]);
    Issue::factory()->for($project)->create(['title' => 'Consectetur fugit sit', 'number' => 2]);
    Issue::factory()->for($project)->create(['title' => 'Delectus ut fugit eligendi', 'number' => 3]);
    Issue::factory()->for($project)->create(['title' => 'Et alia fugit quidem', 'number' => 4]);
    Issue::factory()->for($project)->create(['title' => 'Fugit aut excepturi nihil', 'number' => 5]);
    Issue::factory()->for($project)->create(['title' => 'Beatae fugit rerum', 'number' => 6]);

    $response = $this->actingAs($member)->getJson(palette($organization, 'fugit'));

    expect($response->json('0.title'))->toBe('Fugit aut excepturi nihil');
});

test('an alphabetically late leading match is not cut off by the per type cap', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create(['name' => 'Ranking']);

    foreach (['Aa', 'Ab', 'Ac', 'Ad', 'Ae', 'Af', 'Ag'] as $index => $prefix) {
        Issue::factory()->for($project)->create([
            'title' => "{$prefix} contains fugit here",
            'number' => $index + 1,
        ]);
    }
    Issue::factory()->for($project)->create(['title' => 'Fugit leads the title', 'number' => 99]);

    $response = $this->actingAs($member)->getJson(palette($organization, 'fugit'));

    expect(collect($response->json())->pluck('title'))->toContain('Fugit leads the title');
});

test('an admin finds every other kind of record by what it says', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create(['name' => 'Zephyr Labs']);
    config(['services.resend.support_organization' => $organization->slug]);
    $organization->addMember($admin, 'Admin');
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create();
    $client = Client::factory()->for($organization)->create(['name' => 'Zephyr Co']);
    $contact = ClientContact::factory()->for($client)->create(['name' => 'Zia Zephyr']);

    $client->domains()->create(['domain' => 'zephyr.test']);
    $contact->addresses()->create(['type' => AddressType::Mailing, 'line1' => '1 Zephyr Way', 'city' => 'Dallas']);
    BoardColumn::factory()->for($team, 'owner')->create(['name' => 'Zephyr review']);
    $ticket = Ticket::factory()->forClient($client)->create(['subject' => 'Zephyr login broken']);
    $message = EmailMessage::factory()->for(Comment::factory()->for($ticket, 'commentable')->create(['body' => 'Nothing to see']))->create();
    EmailAttachment::factory()->for($message)->create(['filename' => 'zephyr-screenshot.png']);
    Comment::factory()->for(Issue::factory()->for($project), 'commentable')->create(['body' => 'The zephyr deploy needs a retry']);
    $invoice = Invoice::factory()->for($organization)->for($client)->create(['number' => 'ZEPHYR-0001']);
    InvoiceLine::factory()->for($invoice)->create(['description' => 'Zephyr onboarding']);
    InvoicePayment::factory()->for($invoice)->create(['reference' => 'ZEPHYR-PAY-7']);
    TimeEntry::factory()->for($client)->create(['description' => 'Zephyr call', 'invoice_line_id' => null]);
    WorkType::factory()->for($organization)->create(['name' => 'Zephyr support']);
    $organization->addMember(User::factory()->create(['name' => 'Zed Zephyr']), 'Member');
    $organization->defineRole('Zephyr reviewers', []);

    $response = $this->actingAs($admin)->getJson(palette($organization, 'zephyr'));

    $response->assertOk();

    expect(collect($response->json())->pluck('type')->unique()->sort()->values()->all())
        ->toBe(['address', 'attachment', 'client', 'column', 'comment', 'contact', 'domain', 'invoice', 'invoice line', 'member', 'organization', 'payment', 'role', 'ticket', 'time', 'work type']);
});

test('invoices, tickets and billing records stay hidden from members who cannot manage them', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    config(['services.resend.support_organization' => $organization->slug]);
    $organization->addMember($member, 'Member');
    $client = Client::factory()->for($organization)->create(['name' => 'Quasar']);
    Ticket::factory()->forClient($client)->create(['subject' => 'Quasar outage']);
    Invoice::factory()->for($organization)->for($client)->create(['number' => 'QUASAR-1']);
    WorkType::factory()->for($organization)->create(['name' => 'Quasar work']);

    $types = collect($this->actingAs($member)->getJson(palette($organization, 'quasar'))->json())->pluck('type')->unique()->values()->all();

    expect($types)->toBe(['client']);
});
