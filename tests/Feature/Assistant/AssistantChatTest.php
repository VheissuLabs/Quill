<?php

use App\Ai\Agents\QuillAssistant;
use App\Models\Organization;
use Laravel\Ai\Models\Conversation;

test('a guest cannot reach the assistant', function () {
    auth()->logout();

    $this->get(route('assistant'))->assertRedirect(route('login'));
    $this->post(route('assistant.messages.store'), ['message' => 'Hi'])
        ->assertRedirect(route('login'));
});

test('the assistant page renders with an empty transcript', function () {
    $this
        ->actingAs(userInOrganization())
        ->get(route('assistant'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assistant')
            ->where('messages', []),
        );
});

test('sending a message streams a reply', function () {
    QuillAssistant::fake(['Ready when you are.']);

    $response = $this
        ->actingAs(userInOrganization())
        ->post(route('assistant.messages.store'), ['message' => 'Hello?']);

    $response->assertOk();

    $stream = $response->streamedContent();

    expect(assistantDeltas($stream))->toBe('Ready when you are.');
    expect($stream)->toContain('data: [DONE]');

    QuillAssistant::assertPrompted('Hello?');
});

test('a message is required and bounded', function () {
    QuillAssistant::fake();

    $user = userInOrganization();

    $this->actingAs($user)
        ->post(route('assistant.messages.store'), ['message' => ''])
        ->assertSessionHasErrors('message');

    $this->actingAs($user)
        ->post(route('assistant.messages.store'), ['message' => str_repeat('a', 2001)])
        ->assertSessionHasErrors('message');

    QuillAssistant::assertNeverPrompted();
});

test('the transcript survives a reload', function () {
    /**
     * Keyed on the prompt, not ordered: a streamed invocation advances the fake's
     * response index more than once, so positional fakes do not line up.
     */
    QuillAssistant::fake(fn (string $prompt) => match ($prompt) {
        'First question' => 'The first answer.',
        'Second question' => 'The second answer.',
        default => 'Unexpected prompt.',
    });

    $user = userInOrganization();

    $this->sendToAssistant($user, 'First question');
    $this->sendToAssistant($user, 'Second question');

    $this
        ->actingAs($user)
        ->get(route('assistant'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('messages', 4)
            ->where('messages.0.role', 'user')
            ->where('messages.0.content', 'First question')
            ->where('messages.1.role', 'assistant')
            ->where('messages.3.content', 'The second answer.'),
        );
});

test('a second message continues the same conversation', function () {
    QuillAssistant::fake();

    $user = userInOrganization();

    $this->sendToAssistant($user, 'First');
    $this->sendToAssistant($user, 'Second');

    expect(Conversation::count())->toBe(1);
});

test('one user never sees another user transcript', function () {
    QuillAssistant::fake(['A private answer.']);

    $owner = userInOrganization();
    $stranger = userInOrganization('92 Labs');

    $this->sendToAssistant($owner, 'Something confidential');

    expect($owner->toAssistantMessages())->toHaveCount(2);
    expect($stranger->toAssistantMessages())->toBeEmpty();

    $this
        ->actingAs($stranger)
        ->get(route('assistant'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('messages', []));
});

test('conversations are stored against the uuid participant, not a truncated key', function () {
    QuillAssistant::fake(['Stored.']);

    $user = userInOrganization();

    $this->sendToAssistant($user, 'Store this');

    $conversation = Conversation::sole();

    expect($conversation->participant_id)->toBe($user->id);
    expect($conversation->participant_type)->toBe($user->getMorphClass());
});

test('the prompt names the current organization and forbids inventing data', function () {
    $instructions = new QuillAssistant(userInOrganization())->instructions();

    expect($instructions)
        ->toContain('NotaryDash')
        ->toContain('never invent')
        ->toContain('Use a tool before answering')
        ->toContain('never say you cannot');
});

test('the prompt reflects the organization the user switched to', function () {
    $user = userInOrganization('NotaryDash');
    $other = Organization::factory()->create(['name' => '92 Labs']);

    $other->members()->attach($user);

    $user->assignOrganizationRole($other, 'member');
    $user->switchOrganization($other);

    expect(new QuillAssistant($user->refresh())->instructions())
        ->toContain('92 Labs')
        ->not->toContain('NotaryDash');
});

test('a client contact cannot reach the assistant', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $client = App\Models\Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);
    $contact = contactFor($client, 'Lucy Client');

    $contact->switchOrganization($organization);

    $this->actingAs($contact->refresh())->get(route('assistant'))->assertForbidden();
    $this->actingAs($contact)->post(route('assistant.messages.store'), ['message' => 'Hi'])->assertForbidden();
})->note('Its tools answer for the whole organization, so one client could read another client\'s people.');
