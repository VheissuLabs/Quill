<?php

use App\Events\BillingChanged;
use App\Events\ClientsChanged;
use App\Events\FeedbackChanged;
use App\Events\InvoicesChanged;
use App\Events\MembersChanged;
use App\Events\OrganizationChanged;
use App\Events\ProjectsChanged;
use App\Events\TeamsChanged;
use App\Events\TicketsChanged;
use App\Models\BillingSetting;
use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDomain;
use App\Models\Feedback;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Support\Facades\Event;

test('creating, changing and deleting a record announces it to its organization', function (string $event, Closure $create) {
    $organization = Organization::factory()->create();
    $record = $create($organization);

    Event::fake([$event]);

    $record->forceFill(['updated_at' => now()->addMinute()])->save();
    $announcedOnUpdate = Event::dispatched($event)->count();
    $record->delete();

    expect($announcedOnUpdate)->toBeGreaterThan(0)
        ->and(Event::dispatched($event)->count())->toBeGreaterThan($announcedOnUpdate);
    Event::assertDispatched($event, fn ($dispatched) => $dispatched->organizationId === $organization->id);
})->with([
    'ticket' => [TicketsChanged::class, fn (Organization $organization) => Ticket::factory()->for($organization)->create()],
    'client' => [ClientsChanged::class, fn (Organization $organization) => Client::factory()->for($organization)->create()],
    'client contact' => [ClientsChanged::class, fn (Organization $organization) => ClientContact::factory()->for(Client::factory()->for($organization))->create()],
    'client domain' => [ClientsChanged::class, fn (Organization $organization) => ClientDomain::factory()->for(Client::factory()->for($organization))->create()],
    'invoice' => [InvoicesChanged::class, fn (Organization $organization) => Invoice::factory()->for($organization)->create()],
    'invoice line' => [InvoicesChanged::class, fn (Organization $organization) => InvoiceLine::factory()->for(Invoice::factory()->for($organization))->create()],
    'invoice payment' => [InvoicesChanged::class, fn (Organization $organization) => InvoicePayment::factory()->for(Invoice::factory()->for($organization))->create()],
    'project' => [ProjectsChanged::class, fn (Organization $organization) => Project::factory()->for($organization)->create()],
    'team' => [TeamsChanged::class, fn (Organization $organization) => Team::factory()->for($organization)->create()],
    'team board column' => [TeamsChanged::class, fn (Organization $organization) => BoardColumn::factory()->for(Team::factory()->for($organization), 'owner')->create()],
    'invitation' => [MembersChanged::class, fn (Organization $organization) => OrganizationInvitation::factory()->for($organization)->create()],
    'work type' => [BillingChanged::class, fn (Organization $organization) => WorkType::factory()->for($organization)->create()],
    'billing settings' => [BillingChanged::class, fn (Organization $organization) => BillingSetting::factory()->for($organization)->create()],
]);

test('saving a record with nothing changed announces nothing', function () {
    $client = Client::factory()->create();

    Event::fake([ClientsChanged::class]);

    $client->save();

    Event::assertNotDispatched(ClientsChanged::class);
});

test('changing feedback announces it on its project channel', function () {
    $feedback = Feedback::factory()->create();

    Event::fake([FeedbackChanged::class]);

    $feedback->update(['title' => 'Renamed']);

    Event::assertDispatched(FeedbackChanged::class, fn (FeedbackChanged $event) => $event->projectId === $feedback->project_id
        && $event->feedbackId === $feedback->id);
});

test('joining or leaving a team announces it to the organization', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();

    Event::fake([TeamsChanged::class]);

    $team->members()->attach($member);
    $team->members()->detach($member);

    Event::assertDispatchedTimes(TeamsChanged::class, 2);
});

test('membership and role changes announce it to the organization', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();

    Event::fake([MembersChanged::class]);

    $organization->addMember($member, 'Member');
    $organization->roles()->where('name', 'Member')->firstOrFail()->givePermissionTo([]);
    $organization->removeMember($member);

    Event::assertDispatched(MembersChanged::class, fn (MembersChanged $event) => $event->organizationId === $organization->id);
});

test('renaming an organization announces it', function () {
    $organization = Organization::factory()->create();

    Event::fake([OrganizationChanged::class]);

    $organization->update(['name' => 'Renamed']);

    Event::assertDispatched(OrganizationChanged::class, fn (OrganizationChanged $event) => $event->organizationId === $organization->id);
});
